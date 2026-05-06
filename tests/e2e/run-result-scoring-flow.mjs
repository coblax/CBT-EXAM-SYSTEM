import { runFlowSuite } from './helpers/run-flow-suite.mjs';

runFlowSuite({
    suiteTitle: 'Result & Export',
    specRelativePath: 'tests/e2e/result-scoring.spec.js',
    fixtureKey: 'result_full',
});
