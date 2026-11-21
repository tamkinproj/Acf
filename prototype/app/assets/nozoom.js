/* Phones: the screen never zooms. Pinch gestures are ignored (Safari on iPhone ignores the viewport setting for them),
   and double-tap zoom is turned off in style.css. Text size still follows the phone's own text-size setting. */
(function () {
  var stop = function (e) { e.preventDefault(); };
  ["gesturestart", "gesturechange", "gestureend"].forEach(function (n) { document.addEventListener(n, stop, { passive: false }); });
  document.addEventListener("touchmove", function (e) { if (e.touches && e.touches.length > 1) e.preventDefault(); }, { passive: false });
  // if the page ever ends up zoomed or shifted sideways, put it back at once
  var vv = window.visualViewport;
  if (vv) vv.addEventListener("resize", function () {
    if (vv.scale > 1.01) { var m = document.querySelector('meta[name=viewport]'); if (m) { var c = m.getAttribute("content"); m.setAttribute("content", "width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no"); setTimeout(function () { m.setAttribute("content", c); }, 50); } }
  });
  window.addEventListener("scroll", function () { if (window.scrollX) window.scrollTo(0, window.scrollY); }, { passive: true });
})();
