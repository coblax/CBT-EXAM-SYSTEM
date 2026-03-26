import { runFlowSuite } from './helpers/run-flow-suite.mjs';

runFlowSuite({
    suiteTitle: 'Security Log & Observability',
    specRelativePath: 'tests/e2e/security-log-observability.spec.js',
    fixtureKey: 'security_log_observability',
});
