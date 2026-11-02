package sk.prieboj.priowidget

import android.app.PendingIntent
import android.appwidget.AppWidgetManager
import android.appwidget.AppWidgetProvider
import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.graphics.Color
import android.util.Log
import android.view.View
import android.widget.RemoteViews
import com.nativephp.mobile.R
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL
import java.text.SimpleDateFormat
import java.time.LocalDate
import java.time.format.DateTimeFormatter
import java.util.Date
import java.util.Locale
import kotlin.concurrent.thread

/**
 * Home-screen widget listing the most urgent tasks (same ranking as the app's
 * "Teraz" screen). Fetches GET /api/now itself with the token the app handed
 * over, so it stays current without opening the app (every 30 min, on tap of ↻,
 * and whenever the app changes something).
 */
class PrioWidgetProvider : AppWidgetProvider() {

    companion object {
        private const val TAG = "PrioWidget"
        const val ACTION_REFRESH = "sk.prieboj.priowidget.REFRESH"

        private val ROWS = intArrayOf(R.id.row1, R.id.row2, R.id.row3, R.id.row4, R.id.row5)
        private val PRIOS = intArrayOf(R.id.prio1, R.id.prio2, R.id.prio3, R.id.prio4, R.id.prio5)
        private val TITLES = intArrayOf(R.id.title1, R.id.title2, R.id.title3, R.id.title4, R.id.title5)
        private val METAS = intArrayOf(R.id.meta1, R.id.meta2, R.id.meta3, R.id.meta4, R.id.meta5)

        fun requestRefresh(context: Context) {
            context.sendBroadcast(Intent(context, PrioWidgetProvider::class.java).setAction(ACTION_REFRESH))
        }
    }

    override fun onUpdate(context: Context, appWidgetManager: AppWidgetManager, appWidgetIds: IntArray) {
        refresh(context)
    }

    override fun onReceive(context: Context, intent: Intent) {
        super.onReceive(context, intent)

        if (intent.action == ACTION_REFRESH) {
            refresh(context)
        }
    }

    private fun refresh(context: Context) {
        val app = context.applicationContext
        render(app, loading = true)

        val pending = goAsync()
        thread {
            try {
                fetch(app)
            } catch (e: Exception) {
                Log.w(TAG, "Fetch failed", e)
                PrioWidgetStore.saveError(app, "Offline")
            } finally {
                render(app, loading = false)
                pending.finish()
            }
        }
    }

    private fun fetch(context: Context) {
        val url = PrioWidgetStore.url(context) ?: return
        val token = PrioWidgetStore.token(context) ?: return

        val connection = (URL("$url/api/now?limit=${ROWS.size}").openConnection() as HttpURLConnection).apply {
            connectTimeout = 8000
            readTimeout = 8000
            setRequestProperty("Accept", "application/json")
            setRequestProperty("Authorization", "Bearer $token")
        }

        try {
            when (connection.responseCode) {
                200 -> {
                    PrioWidgetStore.saveCache(context, connection.inputStream.bufferedReader().readText())
                    PrioWidgetStore.saveError(context, null)
                }
                401 -> {
                    PrioWidgetStore.signOut(context)
                    PrioWidgetStore.saveError(context, null)
                }
                else -> PrioWidgetStore.saveError(context, "Chyba ${connection.responseCode}")
            }
        } finally {
            connection.disconnect()
        }
    }

    private fun render(context: Context, loading: Boolean) {
        val views = RemoteViews(context.packageName, R.layout.prio_widget)

        context.packageManager.getLaunchIntentForPackage(context.packageName)?.let { launch ->
            views.setOnClickPendingIntent(
                R.id.widget_root,
                PendingIntent.getActivity(context, 0, launch, PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT)
            )
        }
        views.setOnClickPendingIntent(
            R.id.refresh,
            PendingIntent.getBroadcast(
                context, 1,
                Intent(context, PrioWidgetProvider::class.java).setAction(ACTION_REFRESH),
                PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT
            )
        )

        val signedIn = PrioWidgetStore.token(context) != null
        val data = PrioWidgetStore.cache(context)?.let { runCatching { JSONObject(it) }.getOrNull() }
        val tasks = data?.optJSONArray("tasks")
        val count = if (signedIn) minOf(tasks?.length() ?: 0, ROWS.size) else 0

        for (i in ROWS.indices) {
            if (i >= count) {
                views.setViewVisibility(ROWS[i], View.GONE)
                continue
            }

            val task = tasks!!.getJSONObject(i)
            val priority = task.optInt("priority", 4)

            views.setViewVisibility(ROWS[i], View.VISIBLE)
            views.setTextViewText(PRIOS[i], if (priority == 4) "B" else "P$priority")
            views.setTextColor(PRIOS[i], priorityColor(priority))
            views.setTextViewText(TITLES[i], task.optString("title"))
            views.setTextViewText(METAS[i], meta(task))
        }

        val empty = when {
            !signedIn -> "Prihlás sa v appke Prio Notes."
            data == null -> "Načítavam…"
            count == 0 -> "Nič urgentné 🎉"
            else -> null
        }
        views.setViewVisibility(R.id.empty_text, if (empty == null) View.GONE else View.VISIBLE)
        views.setTextViewText(R.id.empty_text, empty ?: "")

        views.setTextViewText(R.id.header_info, headerInfo(context, data, loading))

        AppWidgetManager.getInstance(context)
            .updateAppWidget(ComponentName(context, PrioWidgetProvider::class.java), views)
    }

    private fun headerInfo(context: Context, data: JSONObject?, loading: Boolean): String {
        if (loading) return "…"
        PrioWidgetStore.error(context)?.let { return it }

        val p1 = data?.optJSONObject("capacity")?.optJSONObject("1")
        val capacity = p1?.let { "P1 ${it.optInt("open")}/${it.optInt("max")}" }
        val updated = PrioWidgetStore.updatedAt(context)
            .takeIf { it > 0 }
            ?.let { SimpleDateFormat("HH:mm", Locale.getDefault()).format(Date(it)) }

        return listOfNotNull(capacity, updated).joinToString(" · ")
    }

    private fun meta(task: JSONObject): String {
        val parts = mutableListOf<String>()

        task.optJSONObject("project")?.let { parts.add("${it.optString("company")} · ${it.optString("name")}") }

        if (!task.isNull("due_date")) {
            runCatching { LocalDate.parse(task.getString("due_date")) }.getOrNull()?.let { due ->
                val today = LocalDate.now()
                parts.add(
                    when {
                        due.isBefore(today) -> "⚠ po termíne"
                        due.isEqual(today) -> "dnes"
                        due.isEqual(today.plusDays(1)) -> "zajtra"
                        else -> due.format(DateTimeFormatter.ofPattern("d. M."))
                    }
                )
            }
        }

        return parts.joinToString(" · ")
    }

    private fun priorityColor(priority: Int): Int = when (priority) {
        1 -> Color.parseColor("#F87171")
        2 -> Color.parseColor("#FBBF24")
        3 -> Color.parseColor("#38BDF8")
        else -> Color.parseColor("#A1A1AA")
    }
}
