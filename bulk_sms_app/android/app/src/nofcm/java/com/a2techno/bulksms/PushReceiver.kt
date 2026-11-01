package com.a2techno.bulksms

import android.app.Service
import android.content.Intent
import android.os.IBinder

/** Stand-in until google-services.json is added. The rebuilt app replaces this with Firebase. */
class PushReceiver : Service() {
    override fun onBind(intent: Intent?): IBinder? = null
}
