package com.a2techno.bulksms

import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

object GatewayLog {
    private val lines = ArrayDeque<String>()
    private val clock = SimpleDateFormat("HH:mm:ss", Locale.getDefault())

    fun add(message: String) {
        val line = clock.format(Date()) + "  " + message
        synchronized(lines) {
            lines.addFirst(line)
            while (lines.size > 40) {
                lines.removeLast()
            }
        }
    }

    fun text(): String {
        synchronized(lines) {
            return if (lines.isEmpty()) "কোনো লগ নেই।" else lines.joinToString("\n")
        }
    }
}
