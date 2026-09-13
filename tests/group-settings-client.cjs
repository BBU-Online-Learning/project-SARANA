const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const window = {};
const document = { querySelector: () => null };
const context = vm.createContext({ window, document, console });

vm.runInContext(fs.readFileSync(path.join(__dirname, '../public/js/chat/group-settings.js'), 'utf8'), context);

const people = [
    { id: 1, name: 'Teacher Dara', role: 'teacher' },
    { id: 2, name: 'Student One', role: 'student' },
    { id: 3, name: 'School Admin', role: 'admin' },
    { id: 4, name: 'Super Admin', role: 'super_admin' },
];
const api = window.GroupSettingsUX;

assert.deepEqual(Array.from(api.filterPeople(people, 'dara', 'all'), user => user.id), [1]);
assert.deepEqual(Array.from(api.filterPeople(people, '', 'student'), user => user.id), [2]);
assert.deepEqual(Array.from(api.filterPeople(people, '', 'admin'), user => user.id), [3, 4]);
assert.deepEqual(Array.from(api.filterPeople(people, 'missing', 'all')), []);
assert.deepEqual(Array.from(api.filterMembers(['teacher dara teacher', 'student one student'], 'teacher')), [true, false]);
assert.deepEqual(Array.from(api.filterMembers(['teacher dara', 'student one'], '')), [true, true]);

console.log('Group settings people search, role filters, empty results and member search checks passed.');
