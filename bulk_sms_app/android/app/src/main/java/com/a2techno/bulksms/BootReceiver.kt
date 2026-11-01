package com.a2techno.bulksms

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent

class BootReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action != Intent.ACTION_BOOT_COMPLETED) {
            return
        }
        if (!GatewayPrefs(context).running) {
            return
        }
        try {
            GatewayService.start(context)
        } catch (_: Exception) {
            GatewayLog.add("Could not restart after boot. Open the app and press Start.")
        }
    }
}
