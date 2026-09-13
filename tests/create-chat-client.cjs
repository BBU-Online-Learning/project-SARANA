const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const window = {};
const document = { getElementById: () => null };
const context = vm.createContext({ window, document, console });

vm.runInContext(fs.readFileSync(path.join(__dirname, '../public/js/chat/create-chat.js'), 'utf8'), context);

const people = [
    { id: 1, name: 'Teacher Dara', role: 'teacher' },
    { id: 2, name: 'John Smith', role: 'student' },
    { id: 3, name: 'Site Admin', role: 'admin' },
    { id: 4, name: 'Super Admin', role: 'super_admin' },
];
const api = window.CreateChatUX;

assert.deepEqual(Array.from(api.filterPeople(people, 'teacher', 'all'), person => person.id), [1]);
assert.deepEqual(Array.from(api.filterPeople(people, '', 'teacher'), person => person.id), [1]);
assert.deepEqual(Array.from(api.filterPeople(people, '', 'admin'), person => person.id), [3, 4]);
assert.deepEqual(Array.from(api.filterPeople(people, 'missing', 'all')), []);
assert(api.validGroupName(' Study group '));
assert(!api.validGroupName('  A '));
assert.deepEqual({ ...api.directPayload(7) }, { user_id: '7' });
assert.deepEqual(
    { ...api.groupPayload('  Project team  ', new Set([2, 4])), members: Array.from(api.groupPayload('Project team', new Set([2, 4])).members) },
    { name: 'Project team', members: ['2', '4'] },
);

console.log('Create chat search, role filtering, empty results, validation and payload checks passed.');
