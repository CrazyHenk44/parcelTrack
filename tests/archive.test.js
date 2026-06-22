import test from 'node:test'
import assert from 'node:assert/strict'
import '../web/archive.js'

const { isArchivedPackage } = globalThis.ParcelTrackArchive

test('archives packages immediately when completed', () => {
  assert.equal(isArchivedPackage({ isCompleted: true, completedAt: null }), true)
  assert.equal(isArchivedPackage({ isCompleted: true, completedAt: new Date().toISOString() }), true)
})

test('completion controls archiving independently from active state', () => {
  assert.equal(isArchivedPackage({ isCompleted: true, inactive: false }), true)
  assert.equal(isArchivedPackage({ isCompleted: false, inactive: true }), false)
  assert.equal(isArchivedPackage(null), false)
})

test('can classify an array without treating its index as the current time', () => {
  const packages = [
    { isCompleted: false, completedAt: null },
    { isCompleted: true, completedAt: '2025-01-01T00:00:00Z' }
  ]

  assert.equal(packages.filter(pkg => isArchivedPackage(pkg)).length, 1)
})
