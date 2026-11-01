package com.a2techno.bulksms

import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage

class PushReceiver : FirebaseMessagingService() {
    override fun onNewToken(token: String) {
        FcmPush.register(this, token)
    }

    override fun onMessageReceived(message: RemoteMessage) {
        val prefs = GatewayPrefs(this)
        if (!prefs.running) {
            return
        }
        GatewayLog.add("Push received. Sending now.")
        GatewayService.start(this, true)
    }
}
