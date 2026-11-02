package sk.prieboj.priowidget

import androidx.fragment.app.FragmentActivity
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.bridge.BridgeResponse

/** Bridge functions called from PHP (Prieboj\PrioWidget\PrioWidget). */
object PrioWidgetFunctions {

    class Configure(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val url = parameters["url"] as? String
            val token = parameters["token"] as? String

            PrioWidgetStore.saveCredentials(activity, url, token)
            PrioWidgetProvider.requestRefresh(activity)

            return BridgeResponse.success()
        }
    }

    class Refresh(private val activity: FragmentActivity) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            PrioWidgetProvider.requestRefresh(activity)

            return BridgeResponse.success()
        }
    }
}
