import { runFlowSuite } from './helpers/run-flow-suite.mjs';

runFlowSuite({
    suiteTitle: 'Question Runtime',
    specRelativePath: 'tests/e2e/question-runtime.spec.js',
    fixtureKey: 'question_runtime',
});
