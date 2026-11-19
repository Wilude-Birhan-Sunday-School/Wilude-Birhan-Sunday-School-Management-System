package com.arkeonethiopia.fkss

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Context
import android.content.Intent
import android.content.pm.ServiceInfo
import android.os.Build
import android.os.IBinder
import androidx.core.app.NotificationCompat

/**
 * P7 — Photo Sync Foreground Service.
 *
 * A START_STICKY-lite started service (we use START_NOT_STICKY because
 * the Dart worker will re-assert state on next app launch; re-creating
 * the service without the worker would show an orphan notification).
 *
 * Responsibilities:
 *   - Hold the process in the "started" state while the Dart
 *     PhotoSyncWorker drains the outbox, so Android does not kill the
 *     app during multi-megabyte uploads when the screen is off.
 *   - Show an ongoing maroon/gold notification: title, progress/total,
 *     percent, and a "Stop sync" action that asks the Dart worker to
 *     pause (does not kill the process).
 *   - Be controllable entirely from Dart via a static method channel
 *     (see CHANNEL in MainActivity): start/update/stop.
 *
 * Why not use flutter_local_notifications / workmanager:
 *   - flutter_local_notifications creates one-shot notifications that
 *     do not elevate process priority (they don't start a
 *     foreground service), so Android can still kill the process.
 *   - workmanager / JobScheduler are throttled by Doze and impose
 *     their own retry/backoff policy which duplicates what PhotoSyncWorker
 *     already does.
 *   - A tiny hand-rolled started service + notification is the smallest
 *     thing that gives us foreground-process priority with no new
 *     dependencies and no conflict with the existing audio_service
 *     foreground service (Android supports multiple concurrent
 *     foreground services on API 29+; on older APIs this notification
 *     simply coexists).
 */
class PhotoSyncForegroundService : Service() {

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        ensureChannel()
        val notif = buildNotification(
            title = intent?.getStringExtra(EXTRA_TITLE) ?: "Uploading photos…",
            content = intent?.getStringExtra(EXTRA_CONTENT) ?: "Preparing…",
            progress = intent?.getIntExtra(EXTRA_PROGRESS, -1) ?: -1,
            max = intent?.getIntExtra(EXTRA_MAX, 0) ?: 0,
            indeterminate = intent?.getBooleanExtra(EXTRA_INDETERMINATE, true) ?: true,
            paused = intent?.getBooleanExtra(EXTRA_PAUSED, false) ?: false,
        )
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.UPSIDE_DOWN_CAKE) {
            startForeground(NOTIFICATION_ID, notif, ServiceInfo.FOREGROUND_SERVICE_TYPE_DATA_SYNC)
        } else {
            startForeground(NOTIFICATION_ID, notif)
        }
        return START_NOT_STICKY
    }

    override fun onDestroy() {
        stopForeground(STOP_FOREGROUND_REMOVE)
        super.onDestroy()
    }

    private fun buildNotification(
        title: String,
        content: String,
        progress: Int,
        max: Int,
        indeterminate: Boolean,
        paused: Boolean,
    ): Notification {
        val openIntent = Intent(this, MainActivity::class.java).apply {
            action = Intent.ACTION_MAIN
            addCategory(Intent.CATEGORY_LAUNCHER)
            addFlags(Intent.FLAG_ACTIVITY_SINGLE_TOP or Intent.FLAG_ACTIVITY_NEW_TASK)
            putExtra("fkss.photo_sync.open_panel", true)
        }
        val openPi = PendingIntent.getActivity(
            this, 0, openIntent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val stopIntent = Intent(this, PhotoSyncActionReceiver::class.java).apply {
            action = PhotoSyncActionReceiver.ACTION_STOP
        }
        val stopPi = PendingIntent.getBroadcast(
            this, 1, stopIntent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )

        val b = NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle(if (paused) "$title (paused)" else title)
            .setContentText(content)
            .setSmallIcon(android.R.drawable.stat_sys_upload)
            .setOngoing(true)
            .setSilent(true) // no sound for progress updates
            .setContentIntent(openPi)
            .setProgress(max, progress, indeterminate)
            .addAction(android.R.drawable.ic_menu_close_clear_cancel, "Stop", stopPi)
            .setCategory(NotificationCompat.CATEGORY_PROGRESS)
            .setVisibility(NotificationCompat.VISIBILITY_PRIVATE)
            .setColor(0xFF004820.toInt()) // WBSS forest green (#004820) as ARGB int
        return b.build()
    }

    private fun ensureChannel() {
        val nm = getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        if (nm.getNotificationChannel(CHANNEL_ID) == null) {
            val ch = NotificationChannel(
                CHANNEL_ID, "Photo sync",
                NotificationManager.IMPORTANCE_LOW
            ).apply {
                description = "Shown while photos are being uploaded."
                setShowBadge(false)
                enableLights(false)
                enableVibration(false)
                setSound(null, null)
            }
            nm.createNotificationChannel(ch)
        }
    }

    companion object {
        const val CHANNEL_ID = "wbss_photo_sync"
        const val NOTIFICATION_ID = 0x57425353 // "WBSS" in hex
        const val EXTRA_TITLE = "title"
        const val EXTRA_CONTENT = "content"
        const val EXTRA_PROGRESS = "progress"
        const val EXTRA_MAX = "max"
        const val EXTRA_INDETERMINATE = "indeterminate"
        const val EXTRA_PAUSED = "paused"
    }
}

/**
 * Handles the "Stop" action on the notification. We don't have a service
 * binder wired, so the simplest path is to broadcast an intent, have
 * this receiver send a one-shot MethodChannel message to the Dart side,
 * and let Dart decide how to pause (same as the in-panel Pause button).
 *
 * The Dart side registers a stream listener for this broadcast in
 * PhotoSyncWorker.
 */
class PhotoSyncActionReceiver : android.content.BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action == ACTION_STOP) {
            // Send a stop command through the existing foreground-service
            // control channel. The Dart side listens for this method call
            // and cancels/pauses the worker.
            try {
                // We need a reference to the engine's binary messenger —
                // use a static hook set by MainActivity on engine attach.
                // If the engine is gone (process just came up), there's
                // nothing to stop.
                val engine = MainActivity.liveEngine?.get() ?: return
                val channel = io.flutter.plugin.common.MethodChannel(
                    engine.dartExecutor.binaryMessenger,
                    "fkss.app/photo_sync_service"
                )
                channel.invokeMethod("stopFromNotification", null)
            } catch (_: Throwable) {
                // Best-effort: if the engine is dead or unavailable, just
                // stop the foreground service. The user may still be in
                // Settings → Force Stop territory.
                val stopIntent = Intent(context, PhotoSyncForegroundService::class.java)
                try { context.stopService(stopIntent) } catch (_: Throwable) {}
            }
        }
    }

    companion object {
        const val ACTION_STOP = "com.arkeonethiopia.fkss.action.STOP_PHOTO_SYNC"
    }
}
