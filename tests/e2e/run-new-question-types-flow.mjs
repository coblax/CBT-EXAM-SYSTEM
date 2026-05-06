import { runFlowSuite } from './helpers/run-flow-suite.mjs';

runFlowSuite({
    suiteTitle: 'New Question Types',
    specRelativePath: 'tests/e2e/new-question-types.spec.js',
    fixtureKey: 'import_preview',
});
