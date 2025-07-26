const fs = require('fs');
const path = require('path');

class CustomReporter {
  constructor(options = {}) {
    this.outputFile = options.outputFile || 'test-results/custom-report.json';
    this.results = {
      summary: {
        total: 0,
        passed: 0,
        failed: 0,
        skipped: 0,
        flaky: 0,
        duration: 0,
        startTime: null,
        endTime: null
      },
      tests: [],
      suites: {},
      environment: {
        node: process.version,
        platform: process.platform,
        arch: process.arch,
        ci: !!process.env.CI,
        buildNumber: process.env.BUILD_NUMBER || 'local',
        gitCommit: process.env.GIT_COMMIT || '',
        gitBranch: process.env.GIT_BRANCH || 'local'
      },
      performance: {
        slowestTests: [],
        averageTestDuration: 0,
        totalExecutionTime: 0
      }
    };
  }

  onBegin(config, suite) {
    this.results.summary.startTime = new Date().toISOString();
    this.results.summary.total = suite.allTests().length;
    
    // Initialize suite tracking
    suite.suites.forEach(s => {
      this.results.suites[s.title] = {
        total: s.allTests().length,
        passed: 0,
        failed: 0,
        skipped: 0,
        duration: 0
      };
    });
  }

  onTestEnd(test, result) {
    const testInfo = {
      title: test.title,
      fullTitle: test.titlePath().join(' › '),
      file: test.location.file,
      line: test.location.line,
      column: test.location.column,
      project: test._projectId,
      status: result.status,
      duration: result.duration,
      retry: result.retry,
      startTime: result.startTime.toISOString(),
      endTime: new Date(result.startTime.getTime() + result.duration).toISOString(),
      error: result.error ? {
        message: result.error.message,
        stack: result.error.stack,
        location: result.error.location
      } : null,
      attachments: result.attachments.map(a => ({
        name: a.name,
        contentType: a.contentType,
        path: a.path
      })),
      annotations: test.annotations.map(a => ({
        type: a.type,
        description: a.description
      }))
    };

    this.results.tests.push(testInfo);

    // Update summary counts
    switch (result.status) {
      case 'passed':
        this.results.summary.passed++;
        break;
      case 'failed':
        this.results.summary.failed++;
        break;
      case 'skipped':
        this.results.summary.skipped++;
        break;
      case 'timedOut':
        this.results.summary.failed++;
        break;
    }

    // Track flaky tests (tests that passed after retry)
    if (result.status === 'passed' && result.retry > 0) {
      this.results.summary.flaky++;
    }

    // Update suite tracking
    const suiteTitle = test.parent.title;
    if (this.results.suites[suiteTitle]) {
      this.results.suites[suiteTitle][result.status]++;
      this.results.suites[suiteTitle].duration += result.duration;
    }

    // Track performance data
    this.results.performance.slowestTests.push({
      title: testInfo.fullTitle,
      duration: result.duration,
      file: testInfo.file,
      project: testInfo.project
    });
  }

  onEnd(result) {
    this.results.summary.endTime = new Date().toISOString();
    this.results.summary.duration = result.duration;
    
    // Sort slowest tests
    this.results.performance.slowestTests.sort((a, b) => b.duration - a.duration);
    this.results.performance.slowestTests = this.results.performance.slowestTests.slice(0, 10);
    
    // Calculate performance metrics
    const totalTestDuration = this.results.tests.reduce((sum, test) => sum + test.duration, 0);
    this.results.performance.averageTestDuration = totalTestDuration / this.results.tests.length;
    this.results.performance.totalExecutionTime = result.duration;

    // Ensure output directory exists
    const outputDir = path.dirname(this.outputFile);
    if (!fs.existsSync(outputDir)) {
      fs.mkdirSync(outputDir, { recursive: true });
    }

    // Write the report
    fs.writeFileSync(this.outputFile, JSON.stringify(this.results, null, 2));
    
    // Generate HTML dashboard
    this.generateHtmlDashboard();
  }

  generateHtmlDashboard() {
    const htmlPath = this.outputFile.replace('.json', '.html');
    const passRate = ((this.results.summary.passed / this.results.summary.total) * 100).toFixed(2);
    
    const html = `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MakeDealCRM E2E Test Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .header { background: #2c3e50; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .metric-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .metric-value { font-size: 2em; font-weight: bold; color: #2c3e50; }
        .metric-label { color: #7f8c8d; font-size: 0.9em; }
        .charts { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .chart-container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .tests-table { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
        .tests-table table { width: 100%; border-collapse: collapse; }
        .tests-table th, .tests-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .tests-table th { background: #f8f9fa; font-weight: bold; }
        .status-passed { color: #27ae60; }
        .status-failed { color: #e74c3c; }
        .status-skipped { color: #f39c12; }
        .performance-section { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>MakeDealCRM E2E Test Dashboard</h1>
        <p>Test execution completed on ${this.results.summary.endTime}</p>
        <p>Build: ${this.results.environment.buildNumber} | Branch: ${this.results.environment.gitBranch}</p>
    </div>

    <div class="metrics">
        <div class="metric-card">
            <div class="metric-value">${this.results.summary.total}</div>
            <div class="metric-label">Total Tests</div>
        </div>
        <div class="metric-card">
            <div class="metric-value status-passed">${this.results.summary.passed}</div>
            <div class="metric-label">Passed</div>
        </div>
        <div class="metric-card">
            <div class="metric-value status-failed">${this.results.summary.failed}</div>
            <div class="metric-label">Failed</div>
        </div>
        <div class="metric-card">
            <div class="metric-value">${passRate}%</div>
            <div class="metric-label">Pass Rate</div>
        </div>
        <div class="metric-card">
            <div class="metric-value">${Math.round(this.results.summary.duration / 1000)}s</div>
            <div class="metric-label">Duration</div>
        </div>
        <div class="metric-card">
            <div class="metric-value status-skipped">${this.results.summary.flaky}</div>
            <div class="metric-label">Flaky Tests</div>
        </div>
    </div>

    <div class="charts">
        <div class="chart-container">
            <h3>Test Results Distribution</h3>
            <canvas id="resultsChart" width="400" height="200"></canvas>
        </div>
        <div class="chart-container">
            <h3>Suite Performance</h3>
            <canvas id="suiteChart" width="400" height="200"></canvas>
        </div>
    </div>

    <div class="performance-section">
        <h3>Performance Metrics</h3>
        <p><strong>Average Test Duration:</strong> ${Math.round(this.results.performance.averageTestDuration)}ms</p>
        <p><strong>Total Execution Time:</strong> ${Math.round(this.results.performance.totalExecutionTime / 1000)}s</p>
        
        <h4>Slowest Tests</h4>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="padding: 8px; border: 1px solid #ddd;">Test</th>
                    <th style="padding: 8px; border: 1px solid #ddd;">Duration</th>
                    <th style="padding: 8px; border: 1px solid #ddd;">Project</th>
                </tr>
            </thead>
            <tbody>
                ${this.results.performance.slowestTests.map(test => `
                    <tr>
                        <td style="padding: 8px; border: 1px solid #ddd;">${test.title}</td>
                        <td style="padding: 8px; border: 1px solid #ddd;">${Math.round(test.duration)}ms</td>
                        <td style="padding: 8px; border: 1px solid #ddd;">${test.project}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    </div>

    <div class="tests-table">
        <h3 style="padding: 20px; margin: 0; background: #f8f9fa;">Test Results</h3>
        <table>
            <thead>
                <tr>
                    <th>Test</th>
                    <th>Status</th>
                    <th>Duration</th>
                    <th>Project</th>
                    <th>Retries</th>
                </tr>
            </thead>
            <tbody>
                ${this.results.tests.map(test => `
                    <tr>
                        <td>${test.fullTitle}</td>
                        <td class="status-${test.status}">${test.status}</td>
                        <td>${Math.round(test.duration)}ms</td>
                        <td>${test.project}</td>
                        <td>${test.retry}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    </div>

    <script>
        // Results pie chart
        const resultsCtx = document.getElementById('resultsChart').getContext('2d');
        new Chart(resultsCtx, {
            type: 'pie',
            data: {
                labels: ['Passed', 'Failed', 'Skipped'],
                datasets: [{
                    data: [${this.results.summary.passed}, ${this.results.summary.failed}, ${this.results.summary.skipped}],
                    backgroundColor: ['#27ae60', '#e74c3c', '#f39c12']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

        // Suite performance bar chart
        const suiteCtx = document.getElementById('suiteChart').getContext('2d');
        const suiteLabels = ${JSON.stringify(Object.keys(this.results.suites))};
        const suiteDurations = ${JSON.stringify(Object.values(this.results.suites).map(s => Math.round(s.duration / 1000)))};
        
        new Chart(suiteCtx, {
            type: 'bar',
            data: {
                labels: suiteLabels,
                datasets: [{
                    label: 'Duration (seconds)',
                    data: suiteDurations,
                    backgroundColor: '#3498db'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>`;

    fs.writeFileSync(htmlPath, html);
  }
}

module.exports = CustomReporter;