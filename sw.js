/**
 * Root service worker — scope: /TiranaSolidare/
 * Delegates all logic to public/sw.js so that pages under /views/ and
 * /public/ are both covered by the same service worker implementation.
 */
importScripts('/TiranaSolidare/public/sw.js');
