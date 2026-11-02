package sk.prieboj.priowidget

import android.content.Context
import android.content.SharedPreferences

/**
 * App-private storage for the widget: server URL, API token and the last
 * task list (JSON) so the widget can draw immediately before fetching.
 */
object PrioWidgetStore {
    private const val PREFS = "prio_widget"

    private fun prefs(context: Context): SharedPreferences =
        context.applicationContext.getSharedPreferences(PREFS, Context.MODE_PRIVATE)

    fun saveCredentials(context: Context, url: String?, token: String?) {
        prefs(context).edit().apply {
            putString("url", url?.trimEnd('/'))
            if (token.isNullOrBlank()) {
                remove("token")
                remove("cache")
            } else {
                putString("token", token)
            }
        }.apply()
    }

    fun url(context: Context): String? = prefs(context).getString("url", null)

    fun token(context: Context): String? = prefs(context).getString("token", null)

    fun cache(context: Context): String? = prefs(context).getString("cache", null)

    fun saveCache(context: Context, json: String) {
        prefs(context).edit().putString("cache", json).putLong("updated", System.currentTimeMillis()).apply()
    }

    fun updatedAt(context: Context): Long = prefs(context).getLong("updated", 0)

    fun signOut(context: Context) {
        prefs(context).edit().remove("token").remove("cache").apply()
    }

    /** Last fetch problem shown in the header, null when the last fetch worked. */
    fun error(context: Context): String? = prefs(context).getString("error", null)

    fun saveError(context: Context, message: String?) {
        prefs(context).edit().putString("error", message).apply()
    }
}
