const fs = require('fs');
const path = require('path');
const os = require('os');

class PerformanceReporter {
  constructor(options = {}) {
    this.outputFile = options.outputFile || 'test-results/performance-report.json';
    this.thresholds = {
      slowTestMs: options.slowTestMs || 10000,
      verySlowTestMs: options.verySlowTestMs || 30000,
      memoryUsageMB: options.memoryUsageMB || 512
    };
    
    this.performance = {
      startTime: null,
      endTime: null,
      totalDuration: 0,
      testCount: 0,
      slowTests: [],
      verySlowTests: [],
      flakyTests: [],
      systemMetrics: {
        startMemory: null,
        endMemory: null,
        peakMemory: null,
        cpuUsage: [],
        platform: process.platform,
        nodeVersion: process.version,
        arch: process.arch
      },
      trends: {
        averageTestDuration: 0,
        medianTestDuration: 0,
        testDurations: [],
        failureRate: 0,
        retryRate: 0
      },
      stability: {
        consistentlyFailing: [],
        intermittentlyFailing: [],
        newFailures: []
      }
    };

    // Start system monitoring
    this.startSystemMonitoring();
  }

  startSystemMonitoring() {
    // Initial memory snapshot
    this.performance.systemMetrics.startMemory = process.memoryUsage();
    
    // Monitor memory usage periodically
    this.memoryMonitor = setInterval(() => {
      const currentMemory = process.memoryUsage();
      if (!this.performance.systemMetrics.peakMemory || 
          currentMemory.heapUsed > this.performance.systemMetrics.peakMemory.heapUsed) {
        this.performance.systemMetrics.peakMemory = currentMemory;
      }
    }, 1000);

    // Monitor CPU usage (simplified)
    this.cpuMonitor = setInterval(() => {
      const loadAvg = os.loadavg();
      this.performance.systemMetrics.cpuUsage.push({
        timestamp: Date.now(),
        load1m: loadAvg[0],
        load5m: loadAvg[1],
        load15m: loadAvg[2]
      });
    }, 5000);
  }

  onBegin(config, suite) {
    this.performance.startTime = Date.now();
    this.performance.testCount = suite.allTests().length;
  }

  onTestEnd(test, result) {
    // Track test duration
    this.performance.trends.testDurations.push(result.duration);

    // Identify slow tests
    if (result.duration > this.thresholds.slowTestMs) {
      this.performance.slowTests.push({
        title: test.titlePath().join(' › '),
        file: test.location.file,
        duration: result.duration,
        project: test._projectId,
        status: result.status
      });
    }

    // Identify very slow tests  
    if (result.duration > this.thresholds.verySlowTestMs) {
      this.performance.verySlowTests.push({
        title: test.titlePath().join(' › '),
        file: test.location.file,
        duration: result.duration,
        project: test._projectId,
        status: result.status
      });
    }

    // Track flaky tests (passed after retry)
    if (result.status === 'passed' && result.retry > 0) {
      this.performance.flakyTests.push({
        title: test.titlePath().join(' › '),
        file: test.location.file,
        retries: result.retry,
        duration: result.duration,
        project: test._projectId
      });
    }

    // Track failed tests for stability analysis
    if (result.status === 'failed') {
      this.trackFailedTest(test, result);
    }
  }

  trackFailedTest(test, result) {
    const testIdentifier = test.titlePath().join(' › ');
    
    // Load historical failure data if available
    const historyFile = path.join(path.dirname(this.outputFile), 'test-history.json');
    let history = {};
    
    if (fs.existsSync(historyFile)) {
      try {
        history = JSON.parse(fs.readFileSync(historyFile, 'utf8'));
      } catch (e) {
        // Continue with empty history if file is corrupted
      }
    }

    if (!history[testIdentifier]) {
      history[testIdentifier] = {
        failures: [],
        totalRuns: 0,
        lastSeen: null
      };
    }

    history[testIdentifier].failures.push({
      timestamp: Date.now(),
      error: result.error ? result.error.message : 'Unknown error',
      duration: result.duration,
      retry: result.retry
    });
    history[testIdentifier].totalRuns++;
    history[testIdentifier].lastSeen = Date.now();

    // Classify failure patterns
    const recentFailures = history[testIdentifier].failures.filter(
      f => Date.now() - f.timestamp < 7 * 24 * 60 * 60 * 1000 // Last 7 days
    );

    if (recentFailures.length >= 5) {
      this.performance.stability.consistentlyFailing.push({
        test: testIdentifier,
        failureCount: recentFailures.length,
        lastFailure: new Date(history[testIdentifier].lastSeen).toISOString()
      });
    } else if (recentFailures.length >= 2) {
      this.performance.stability.intermittentlyFailing.push({
        test: testIdentifier,
        failureCount: recentFailures.length,
        lastFailure: new Date(history[testIdentifier].lastSeen).toISOString()
      });
    } else if (recentFailures.length === 1) {
      this.performance.stability.newFailures.push({
        test: testIdentifier,
        error: result.error ? result.error.message : 'Unknown error',
        duration: result.duration
      });
    }

    // Save updated history
    fs.writeFileSync(historyFile, JSON.stringify(history, null, 2));
  }

  onEnd(result) {
    this.performance.endTime = Date.now();
    this.performance.totalDuration = result.duration;
    this.performance.systemMetrics.endMemory = process.memoryUsage();

    // Stop monitoring
    if (this.memoryMonitor) clearInterval(this.memoryMonitor);
    if (this.cpuMonitor) clearInterval(this.cpuMonitor);

    // Calculate trends
    this.calculateTrends();
    
    // Generate performance insights
    this.generateInsights();

    // Save the report
    this.saveReport();
    
    // Generate performance HTML report
    this.generatePerformanceHtml();
  }

  calculateTrends() {
    const durations = this.performance.trends.testDurations.sort((a, b) => a - b);
    
    // Average duration
    this.performance.trends.averageTestDuration = 
      durations.reduce((sum, d) => sum + d, 0) / durations.length;
    
    // Median duration
    const mid = Math.floor(durations.length / 2);
    this.performance.trends.medianTestDuration = 
      durations.length % 2 === 0 
        ? (durations[mid - 1] + durations[mid]) / 2 
        : durations[mid];

    // Failure and retry rates
    this.performance.trends.failureRate = 
      (this.performance.stability.consistentlyFailing.length + 
       this.performance.stability.intermittentlyFailing.length + 
       this.performance.stability.newFailures.length) / this.performance.testCount;
    
    this.performance.trends.retryRate = 
      this.performance.flakyTests.length / this.performance.testCount;
  }

  generateInsights() {
    this.insights = {
      performance: [],
      stability: [],
      resource: []
    };

    // Performance insights
    if (this.performance.trends.averageTestDuration > 5000) {
      this.insights.performance.push({
        type: 'warning',
        message: `Average test duration (${Math.round(this.performance.trends.averageTestDuration)}ms) is above recommended 5s threshold`,
        recommendation: 'Consider optimizing slow tests or breaking them into smaller units'
      });
    }

    if (this.performance.verySlowTests.length > 0) {
      this.insights.performance.push({
        type: 'error',
        message: `${this.performance.verySlowTests.length} tests exceed ${this.thresholds.verySlowTestMs}ms duration`,
        recommendation: 'Review and optimize these tests as they significantly impact CI/CD pipeline performance'
      });
    }

    // Stability insights
    if (this.performance.trends.failureRate > 0.1) {
      this.insights.stability.push({
        type: 'error',
        message: `High failure rate: ${(this.performance.trends.failureRate * 100).toFixed(1)}%`,
        recommendation: 'Investigate and fix consistently failing tests'
      });
    }

    if (this.performance.flakyTests.length > 0) {
      this.insights.stability.push({
        type: 'warning',
        message: `${this.performance.flakyTests.length} flaky tests detected`,
        recommendation: 'Flaky tests reduce confidence in the test suite and should be fixed'
      });
    }

    // Resource insights
    const peakMemoryMB = this.performance.systemMetrics.peakMemory.heapUsed / 1024 / 1024;
    if (peakMemoryMB > this.thresholds.memoryUsageMB) {
      this.insights.resource.push({
        type: 'warning',
        message: `Peak memory usage (${Math.round(peakMemoryMB)}MB) exceeds threshold`,
        recommendation: 'Consider running tests with more memory or optimizing memory-intensive tests'
      });
    }
  }

  saveReport() {
    const outputDir = path.dirname(this.outputFile);
    if (!fs.existsSync(outputDir)) {
      fs.mkdirSync(outputDir, { recursive: true });
    }

    const report = {
      ...this.performance,
      insights: this.insights,
      generatedAt: new Date().toISOString(),
      reportVersion: '1.0.0'
    };

    fs.writeFileSync(this.outputFile, JSON.stringify(report, null, 2));
  }

  generatePerformanceHtml() {
    const htmlPath = this.outputFile.replace('.json', '.html');
    const peakMemoryMB = Math.round(this.performance.systemMetrics.peakMemory.heapUsed / 1024 / 1024);
    const avgDuration = Math.round(this.performance.trends.averageTestDuration);
    const totalDurationSec = Math.round(this.performance.totalDuration / 1000);

    const html = `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MakeDealCRM E2E Performance Report</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .header { background: #8e44ad; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .metric-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .metric-value { font-size: 2em; font-weight: bold; color: #8e44ad; }
        .metric-label { color: #7f8c8d; font-size: 0.9em; }
        .insights { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .insight { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .insight.error { background: #ffebee; border-left: 4px solid #f44336; }
        .insight.warning { background: #fff3e0; border-left: 4px solid #ff9800; }
        .insight.info { background: #e3f2fd; border-left: 4px solid #2196f3; }
        .charts { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        .chart-container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .table-container { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; margin-bottom: 20px; }
        .table-container table { width: 100%; border-collapse: collapse; }
        .table-container th, .table-container td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .table-container th { background: #f8f9fa; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>MakeDealCRM E2E Performance Report</h1>
        <p>Performance analysis generated on ${new Date().toISOString()}</p>
    </div>

    <div class="metrics">
        <div class="metric-card">
            <div class="metric-value">${totalDurationSec}s</div>
            <div class="metric-label">Total Execution Time</div>
        </div>
        <div class="metric-card">
            <div class="metric-value">${avgDuration}ms</div>
            <div class="metric-label">Average Test Duration</div>
        </div>
        <div class="metric-card">
            <div class="metric-value">${peakMemoryMB}MB</div>
            <div class="metric-label">Peak Memory Usage</div>
        </div>
        <div class="metric-card">
            <div class="metric-value">${this.performance.slowTests.length}</div>
            <div class="metric-label">Slow Tests (>${this.thresholds.slowTestMs / 1000}s)</div>
        </div>
        <div class="metric-card">
            <div class="metric-value">${this.performance.flakyTests.length}</div>
            <div class="metric-label">Flaky Tests</div>
        </div>
        <div class="metric-card">
            <div class="metric-value">${(this.performance.trends.failureRate * 100).toFixed(1)}%</div>
            <div class="metric-label">Failure Rate</div>
        </div>
    </div>

    <div class="insights">
        <h3>Performance Insights</h3>
        ${this.insights.performance.map(insight => `
            <div class="insight ${insight.type}">
                <strong>${insight.message}</strong><br>
                <em>Recommendation: ${insight.recommendation}</em>
            </div>
        `).join('')}
        
        <h3>Stability Insights</h3>
        ${this.insights.stability.map(insight => `
            <div class="insight ${insight.type}">
                <strong>${insight.message}</strong><br>
                <em>Recommendation: ${insight.recommendation}</em>
            </div>
        `).join('')}
        
        <h3>Resource Insights</h3>
        ${this.insights.resource.map(insight => `
            <div class="insight ${insight.type}">
                <strong>${insight.message}</strong><br>
                <em>Recommendation: ${insight.recommendation}</em>
            </div>
        `).join('')}
    </div>

    <div class="charts">
        <div class="chart-container">
            <h3>Test Duration Distribution</h3>
            <canvas id="durationChart" width="400" height="200"></canvas>
        </div>
        <div class="chart-container">
            <h3>Memory Usage Over Time</h3>
            <canvas id="memoryChart" width="400" height="200"></canvas>
        </div>
    </div>

    ${this.performance.slowTests.length > 0 ? `
    <div class="table-container">
        <h3 style="padding: 20px; margin: 0; background: #f8f9fa;">Slowest Tests</h3>
        <table>
            <thead>
                <tr>
                    <th>Test</th>
                    <th>Duration</th>
                    <th>Project</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                ${this.performance.slowTests.slice(0, 10).map(test => `
                    <tr>
                        <td>${test.title}</td>
                        <td>${Math.round(test.duration)}ms</td>
                        <td>${test.project}</td>
                        <td>${test.status}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    </div>` : ''}

    ${this.performance.flakyTests.length > 0 ? `
    <div class="table-container">
        <h3 style="padding: 20px; margin: 0; background: #f8f9fa;">Flaky Tests</h3>
        <table>
            <thead>
                <tr>
                    <th>Test</th>
                    <th>Retries</th>
                    <th>Duration</th>
                    <th>Project</th>
                </tr>
            </thead>
            <tbody>
                ${this.performance.flakyTests.map(test => `
                    <tr>
                        <td>${test.title}</td>
                        <td>${test.retries}</td>
                        <td>${Math.round(test.duration)}ms</td>
                        <td>${test.project}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    </div>` : ''}

    <script>
        // Duration distribution histogram
        const durationCtx = document.getElementById('durationChart').getContext('2d');
        const durations = ${JSON.stringify(this.performance.trends.testDurations)};
        
        // Create buckets for histogram
        const buckets = [0, 1000, 2000, 5000, 10000, 30000, Infinity];
        const bucketLabels = ['<1s', '1-2s', '2-5s', '5-10s', '10-30s', '>30s'];
        const bucketCounts = new Array(buckets.length - 1).fill(0);
        
        durations.forEach(duration => {
            for (let i = 0; i < buckets.length - 1; i++) {
                if (duration >= buckets[i] && duration < buckets[i + 1]) {
                    bucketCounts[i]++;
                    break;
                }
            }
        });

        new Chart(durationCtx, {
            type: 'bar',
            data: {
                labels: bucketLabels,
                datasets: [{
                    label: 'Test Count',
                    data: bucketCounts,
                    backgroundColor: '#8e44ad'
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

        // Memory usage chart (simplified)
        const memoryCtx = document.getElementById('memoryChart').getContext('2d');
        const startMB = ${Math.round(this.performance.systemMetrics.startMemory.heapUsed / 1024 / 1024)};
        const peakMB = ${peakMemoryMB};
        const endMB = ${Math.round(this.performance.systemMetrics.endMemory.heapUsed / 1024 / 1024)};

        new Chart(memoryCtx, {
            type: 'line',
            data: {
                labels: ['Start', 'Peak', 'End'],
                datasets: [{
                    label: 'Memory Usage (MB)',
                    data: [startMB, peakMB, endMB],
                    backgroundColor: 'rgba(142, 68, 173, 0.2)',
                    borderColor: '#8e44ad',
                    borderWidth: 2,
                    fill: true
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

module.exports = PerformanceReporter;