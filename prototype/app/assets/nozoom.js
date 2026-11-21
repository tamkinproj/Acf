/* Phones: the screen never zooms. Pinch gestures are ignored (Safari on iPhone ignores the viewport setting for them),
   and double-tap zoom is turned off in style.css. Text size still follows the phone's own text-size setting. */
(function () {
  var stop = function (e) { e.preventDefault(); };
  ["gesturestart", "gesturechange", "gestureend"].forEach(function (n) { document.addEventListener(n, stop, { passive: false }); });
  document.addEventListener("touchmove", function (e) { if (e.touches && e.touches.length > 1) e.preventDefault(); }, { passive: false });
})();
