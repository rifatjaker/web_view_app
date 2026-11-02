package com.a2techno.bulksms

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.os.Build
import android.telephony.SubscriptionManager
import android.telephony.TelephonyManager
import androidx.core.content.ContextCompat

object SimNumbers {
    fun number(context: Context, subscriptionId: Int): String {
        val found = LinkedHashSet<String>()
        fun add(raw: String?) {
            val cleaned = raw.orEmpty().filter { it.isDigit() || it == '+' }.take(20)
            if (cleaned.count { it.isDigit() } >= 8) {
                found.add(cleaned)
            }
        }

        val phoneState = ContextCompat.checkSelfPermission(
            context,
            Manifest.permission.READ_PHONE_STATE
        ) == PackageManager.PERMISSION_GRANTED
        val phoneNumbers = ContextCompat.checkSelfPermission(
            context,
            Manifest.permission.READ_PHONE_NUMBERS
        ) == PackageManager.PERMISSION_GRANTED
        if (!phoneState && !phoneNumbers) {
            return ""
        }

        val subscriptions = context.getSystemService(SubscriptionManager::class.java)
        val info = if (subscriptions != null && phoneState) {
            try {
                subscriptions.activeSubscriptionInfoList?.firstOrNull { it.subscriptionId == subscriptionId }
            } catch (_: SecurityException) {
                null
            }
        } else {
            null
        }
        if (info != null) {
            @Suppress("DEPRECATION")
            add(info.number)
        }
        if (subscriptions != null && phoneNumbers && Build.VERSION.SDK_INT >= 33) {
            try {
                add(subscriptions.getPhoneNumber(subscriptionId))
            } catch (_: SecurityException) {
            }
        }

        val telephony = context.getSystemService(TelephonyManager::class.java)
        val scoped = if (telephony != null && subscriptionId >= 0) {
            telephony.createForSubscriptionId(subscriptionId)
        } else {
            telephony
        }
        try {
            @Suppress("DEPRECATION")
            add(scoped?.line1Number)
        } catch (_: SecurityException) {
        }

        return found.firstOrNull().orEmpty()
    }

    fun same(left: String, right: String): Boolean {
        val a = left.filter { it.isDigit() }
        val b = right.filter { it.isDigit() }
        if (a.length < 10 || b.length < 10) {
            return a.isNotEmpty() && a == b
        }
        return a.takeLast(10) == b.takeLast(10)
    }
}
