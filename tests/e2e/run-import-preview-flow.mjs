import { runFlowSuite } from './helpers/run-flow-suite.mjs';

runFlowSuite({
    suiteTitle: 'Import & Preview',
    specRelativePath: 'tests/e2e/import-preview.spec.js',
    fixtureKey: 'import_preview',
});
