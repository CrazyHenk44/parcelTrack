(function (root, factory) {
  const api = factory()
  if (typeof module === 'object' && module.exports) module.exports = api
  else root.ParcelTrackArchive = api
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  'use strict'

  function isArchivedPackage(pkg) {
    return pkg?.isCompleted === true
  }

  return { isArchivedPackage }
})
