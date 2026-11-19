import 'package:flutter/material.dart';

/// Smooth page transitions — replaces jarring MaterialPageRoute
class SmoothPageRoute<T> extends PageRouteBuilder<T> {
  final Widget page;

  SmoothPageRoute({required this.page, RouteSettings? settings})
      : super(
          settings: settings,
          pageBuilder: (_, __, ___) => page,
          transitionsBuilder: (_, anim, __, child) {
            return FadeTransition(
              opacity: CurvedAnimation(parent: anim, curve: Curves.easeOut),
              child: SlideTransition(
                position: Tween<Offset>(
                  begin: const Offset(0.03, 0),
                  end: Offset.zero,
                ).animate(CurvedAnimation(parent: anim, curve: Curves.easeOut)),
                child: child,
              ),
            );
          },
          transitionDuration: const Duration(milliseconds: 250),
          reverseTransitionDuration: const Duration(milliseconds: 200),
        );
}

/// Extension for easy navigation
extension NavigatorX on BuildContext {
  Future<T?> pushSmooth<T>(Widget page, {RouteSettings? settings}) {
    return Navigator.of(this).push<T>(SmoothPageRoute<T>(page: page, settings: settings));
  }

  void pushReplacementSmooth(Widget page, {RouteSettings? settings}) {
    Navigator.of(this).pushReplacement(SmoothPageRoute(page: page, settings: settings));
  }

  void pushAndClearSmooth(Widget page, {RouteSettings? settings}) {
    Navigator.of(this).pushAndRemoveUntil(
      SmoothPageRoute(page: page, settings: settings),
      (route) => false,
    );
  }
}
