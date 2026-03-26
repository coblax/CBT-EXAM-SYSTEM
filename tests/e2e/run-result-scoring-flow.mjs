import { runFlowSuite } from './helpers/run-flow-suite.mjs';

runFlowSuite({
    suiteTitle: 'Result & Scoring',
    specRelativePath: 'tests/e2e/result-scoring.spec.js',
    fixtureKey: 'result_full',
});
