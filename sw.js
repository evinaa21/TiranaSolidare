/**
 * Root service worker — scope: /TiranaSolidare/
 * Delegates all logic to public/sw.js so that pages under /views/ and
 * /public/ are both covered by the same service worker implementation.
 */
const swBasePath = new URL(self.location.href).pathname.replace(/\/sw\.js$/, '');
importScripts(`${swBasePath}/public/sw.js`);
