import Flutter
import UIKit

@main
@objc class AppDelegate: FlutterAppDelegate {
  override func application(
    _ application: UIApplication,
    didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
  ) -> Bool {
    GeneratedPluginRegistrant.register(with: self)

    // P7 — register the no-cloud-backup method channel. Photos captured
    // offline live in the app-support directory (not in iCloud Documents
    // and not visible to iTunes file sharing), but we also set the
    // NSURLIsExcludedFromBackupKey resource flag explicitly so iOS never
    // tries to upload queue/export files to iCloud. The flag is set
    // per-directory so any future files added beneath the directory are
    // also excluded.
    let controller = window?.rootViewController as! FlutterViewController
    let noCloudChannel = FlutterMethodChannel(
      name: "app.fkss.photo/nocloud",
      binaryMessenger: controller.binaryMessenger)
    noCloudChannel.setMethodCallHandler { call, result in
      if call.method == "excludeFromBackup" {
        guard let args = call.arguments as? [String: Any],
              let path = args["path"] as? String else {
          result(false)
          return
        }
        let url = URL(fileURLWithPath: path)
        do {
          var rv = URLResourceValues()
          rv.isExcludedFromBackup = true
          var target = url
          // Apply to the file/dir itself.
          try target.setResourceValues(rv)
          // Also apply to the parent queue/exports root if we can find it
          // (so sibling files created later are covered).
          target.deleteLastPathComponent()
          var rootValues = URLResourceValues()
          rootValues.isExcludedFromBackup = true
          try? target.setResourceValues(rootValues)
          result(true)
        } catch {
          result(false)
        }
      } else {
        result(FlutterMethodNotImplemented)
      }
    }

    return super.application(application, didFinishLaunchingWithOptions: launchOptions)
  }
}
