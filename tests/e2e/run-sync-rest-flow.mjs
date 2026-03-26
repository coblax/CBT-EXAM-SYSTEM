import { runFlowSuite } from './helpers/run-flow-suite.mjs';

runFlowSuite({
    suiteTitle: 'Sync & REST',
    specRelativePath: 'tests/e2e/sync-rest.spec.js',
    fixtureKey: 'sync_rest',
});
