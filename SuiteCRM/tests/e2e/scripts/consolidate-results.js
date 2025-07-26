const fs = require('fs');
const path = require('path');

class ResultsConsolidator {
  constructor(artifactsDir = '../../../downloaded-artifacts/') {
    this.artifactsDir = path.resolve(__dirname, artifactsDir);
    this.outputDir = path.resolve(__dirname, '../test-results');
    this.consolidatedReport = {
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
      shards: {},
      browsers: {},
      environment: {
        buildNumber: process.env.BUILD_NUMBER || 'local',
        buildUrl: process.env.BUILD_URL || '',
        gitCommit: process.env.GIT_COMMIT || '',
        gitBranch: process.env.GIT_BRANCH || 'local',
        timestamp: new Date().toISOString()
      },
      performance: {
        slowestTests: [],
        averageTestDuration: 0,
        totalExecutionTime: 0,
        memoryUsage: null
      }
    };
  }

  async consolidate() {
    console.log('Starting result consolidation...');
    
    if (!fs.existsSync(this.artifactsDir)) {
      console.error(`Artifacts directory not found: ${this.artifactsDir}`);
      process.exit(1);
    }

    // Ensure output directory exists
    if (!fs.existsSync(this.outputDir)) {
      fs.mkdirSync(this.outputDir, { recursive: true });
    }

    await this.processArtifacts();
    await this.calculateAggregates();
    await this.generateConsolidatedReport();
    await this.generateJunitReport();
    await this.generatePerformanceSummary();

    console.log('Result consolidation completed');
  }

  async processArtifacts() {
    const artifactDirs = fs.readdirSync(this.artifactsDir, { withFileTypes: true })
      .filter(dirent => dirent.isDirectory())
      .map(dirent => dirent.name);

    console.log(`Found ${artifactDirs.length} artifact directories`);

    for (const dirName of artifactDirs) {
      const dirPath = path.join(this.artifactsDir, dirName);
      await this.processArtifactDirectory(dirName, dirPath);
    }
  }

  async processArtifactDirectory(dirName, dirPath) {
    console.log(`Processing artifact directory: ${dirName}`);
    
    // Extract shard and browser info from directory name
    // Expected format: test-results-{browser}-shard-{number}
    const match = dirName.match(/test-results-(.+)-shard-(\d+)/);
    const browser = match ? match[1] : 'unknown';
    const shard = match ? parseInt(match[2]) : 0;

    // Initialize tracking objects
    if (!this.consolidatedReport.browsers[browser]) {
      this.consolidatedReport.browsers[browser] = {
        total: 0,
        passed: 0,
        failed: 0,
        skipped: 0,
        duration: 0
      };
    }

    if (!this.consolidatedReport.shards[`${browser}-${shard}`]) {
      this.consolidatedReport.shards[`${browser}-${shard}`] = {
        browser,
        shard,
        total: 0,
        passed: 0,
        failed: 0,
        skipped: 0,
        duration: 0
      };
    }

    // Process individual result files
    const files = this.findResultFiles(dirPath);
    
    for (const file of files) {
      await this.processResultFile(file, browser, shard);
    }
  }

  findResultFiles(dirPath) {
    const files = [];
    
    // Look for various result file types
    const patterns = [
      'custom-report.json',
      'performance-report.json',
      'test-results.json',
      'junit.xml'
    ];

    function searchRecursively(currentPath) {
      const items = fs.readdirSync(currentPath, { withFileTypes: true });
      
      for (const item of items) {
        const fullPath = path.join(currentPath, item.name);
        
        if (item.isDirectory()) {
          searchRecursively(fullPath);
        } else if (patterns.some(pattern => item.name.includes(pattern.replace('.json', '').replace('.xml', '')))) {
          files.push(fullPath);
        }
      }
    }

    if (fs.existsSync(dirPath)) {
      searchRecursively(dirPath);
    }

    return files;
  }

  async processResultFile(filePath, browser, shard) {
    try {
      const fileName = path.basename(filePath);
      
      if (fileName.includes('custom-report') && fileName.endsWith('.json')) {
        await this.processCustomReport(filePath, browser, shard);
      } else if (fileName.includes('performance-report') && fileName.endsWith('.json')) {
        await this.processPerformanceReport(filePath, browser, shard);
      } else if (fileName.includes('test-results') && fileName.endsWith('.json')) {
        await this.processPlaywrightReport(filePath, browser, shard);
      }
    } catch (error) {
      console.error(`Error processing file ${filePath}:`, error.message);
    }
  }

  async processCustomReport(filePath, browser, shard) {
    const content = JSON.parse(fs.readFileSync(filePath, 'utf8'));
    
    // Merge summary data
    this.consolidatedReport.summary.total += content.summary.total;
    this.consolidatedReport.summary.passed += content.summary.passed;
    this.consolidatedReport.summary.failed += content.summary.failed;
    this.consolidatedReport.summary.skipped += content.summary.skipped;
    this.consolidatedReport.summary.flaky += content.summary.flaky;
    this.consolidatedReport.summary.duration += content.summary.duration;

    // Track earliest start and latest end times
    if (!this.consolidatedReport.summary.startTime || 
        new Date(content.summary.startTime) < new Date(this.consolidatedReport.summary.startTime)) {
      this.consolidatedReport.summary.startTime = content.summary.startTime;
    }
    
    if (!this.consolidatedReport.summary.endTime || 
        new Date(content.summary.endTime) > new Date(this.consolidatedReport.summary.endTime)) {
      this.consolidatedReport.summary.endTime = content.summary.endTime;
    }

    // Merge tests with additional metadata
    if (content.tests) {
      content.tests.forEach(test => {
        this.consolidatedReport.tests.push({
          ...test,
          browser,
          shard,
          artifactSource: filePath
        });
      });
    }

    // Merge suites
    if (content.suites) {
      Object.keys(content.suites).forEach(suiteName => {
        if (!this.consolidatedReport.suites[suiteName]) {
          this.consolidatedReport.suites[suiteName] = {
            total: 0,
            passed: 0,
            failed: 0,
            skipped: 0,
            duration: 0,
            browsers: {}
          };
        }
        
        const suite = content.suites[suiteName];
        this.consolidatedReport.suites[suiteName].total += suite.total;
        this.consolidatedReport.suites[suiteName].passed += suite.passed;
        this.consolidatedReport.suites[suiteName].failed += suite.failed;
        this.consolidatedReport.suites[suiteName].skipped += suite.skipped;
        this.consolidatedReport.suites[suiteName].duration += suite.duration;
        
        if (!this.consolidatedReport.suites[suiteName].browsers[browser]) {
          this.consolidatedReport.suites[suiteName].browsers[browser] = { ...suite };
        }
      });
    }

    // Update browser and shard tracking
    this.consolidatedReport.browsers[browser].total += content.summary.total;
    this.consolidatedReport.browsers[browser].passed += content.summary.passed;
    this.consolidatedReport.browsers[browser].failed += content.summary.failed;
    this.consolidatedReport.browsers[browser].skipped += content.summary.skipped;
    this.consolidatedReport.browsers[browser].duration += content.summary.duration;

    const shardKey = `${browser}-${shard}`;
    this.consolidatedReport.shards[shardKey].total += content.summary.total;
    this.consolidatedReport.shards[shardKey].passed += content.summary.passed;
    this.consolidatedReport.shards[shardKey].failed += content.summary.failed;
    this.consolidatedReport.shards[shardKey].skipped += content.summary.skipped;
    this.consolidatedReport.shards[shardKey].duration += content.summary.duration;
  }

  async processPerformanceReport(filePath, browser, shard) {
    const content = JSON.parse(fs.readFileSync(filePath, 'utf8'));
    
    // Merge performance data
    if (content.performance) {
      // Merge slowest tests
      if (content.performance.slowestTests) {
        this.consolidatedReport.performance.slowestTests.push(
          ...content.performance.slowestTests.map(test => ({
            ...test,
            browser,
            shard
          }))
        );
      }

      // Track memory usage
      if (content.systemMetrics && content.systemMetrics.peakMemory) {
        if (!this.consolidatedReport.performance.memoryUsage) {
          this.consolidatedReport.performance.memoryUsage = {
            peak: content.systemMetrics.peakMemory,
            browser,
            shard
          };
        } else if (content.systemMetrics.peakMemory.heapUsed > 
                   this.consolidatedReport.performance.memoryUsage.peak.heapUsed) {
          this.consolidatedReport.performance.memoryUsage = {
            peak: content.systemMetrics.peakMemory,
            browser,
            shard
          };
        }
      }
    }
  }

  async processPlaywrightReport(filePath, browser, shard) {
    // Process standard Playwright JSON reports
    const content = JSON.parse(fs.readFileSync(filePath, 'utf8'));
    
    if (content.suites) {
      // This is a Playwright test result format
      // Process and extract relevant data
      console.log(`Processing Playwright report from ${browser}-${shard}`);
    }
  }

  async calculateAggregates() {
    // Calculate performance aggregates
    if (this.consolidatedReport.tests.length > 0) {
      const totalDuration = this.consolidatedReport.tests.reduce((sum, test) => sum + test.duration, 0);
      this.consolidatedReport.performance.averageTestDuration = totalDuration / this.consolidatedReport.tests.length;
      this.consolidatedReport.performance.totalExecutionTime = this.consolidatedReport.summary.duration;
    }

    // Sort and limit slowest tests
    this.consolidatedReport.performance.slowestTests.sort((a, b) => b.duration - a.duration);
    this.consolidatedReport.performance.slowestTests = this.consolidatedReport.performance.slowestTests.slice(0, 20);

    // Calculate pass rates
    this.consolidatedReport.summary.passRate = 
      this.consolidatedReport.summary.total > 0 
        ? (this.consolidatedReport.summary.passed / this.consolidatedReport.summary.total) * 100 
        : 0;

    Object.keys(this.consolidatedReport.browsers).forEach(browser => {
      const browserData = this.consolidatedReport.browsers[browser];
      browserData.passRate = browserData.total > 0 ? (browserData.passed / browserData.total) * 100 : 0;
    });
  }

  async generateConsolidatedReport() {
    const reportPath = path.join(this.outputDir, 'consolidated-report.json');
    fs.writeFileSync(reportPath, JSON.stringify(this.consolidatedReport, null, 2));
    
    // Generate HTML version
    const htmlPath = path.join(this.outputDir, 'consolidated-report.html');
    const htmlContent = this.generateConsolidatedHtml();
    fs.writeFileSync(htmlPath, htmlContent);
    
    console.log(`Consolidated report saved to: ${reportPath}`);
    console.log(`Consolidated HTML report saved to: ${htmlPath}`);
  }

  generateConsolidatedHtml() {
    const passRate = this.consolidatedReport.summary.passRate.toFixed(1);
    const duration = Math.round(this.consolidatedReport.summary.duration / 1000);
    
    return `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consolidated E2E Test Report</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .header { background: #2c3e50; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .metric-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .metric-value { font-size: 2em; font-weight: bold; color: #2c3e50; }
        .section { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .charts { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Consolidated E2E Test Report</h1>
        <p>Build #${this.consolidatedReport.environment.buildNumber} | ${this.consolidatedReport.environment.gitBranch}</p>
        <p>Generated on ${new Date(this.consolidatedReport.environment.timestamp).toLocaleString()}</p>
    </div>

    <div class="metrics">
        <div class="metric-card">
            <div class="metric-value">${this.consolidatedReport.summary.total}</div>
            <div>Total Tests</div>
        </div>
        <div class="metric-card">
            <div class="metric-value" style="color: #27ae60">${this.consolidatedReport.summary.passed}</div>
            <div>Passed</div>
        </div>
        <div class="metric-card">
            <div class="metric-value" style="color: #e74c3c">${this.consolidatedReport.summary.failed}</div>
            <div>Failed</div>
        </div>
        <div class="metric-card">
            <div class="metric-value">${passRate}%</div>
            <div>Pass Rate</div>
        </div>
        <div class="metric-card">
            <div class="metric-value">${duration}s</div>
            <div>Total Duration</div>
        </div>
        <div class="metric-card">
            <div class="metric-value">${this.consolidatedReport.summary.flaky}</div>
            <div>Flaky Tests</div>
        </div>
    </div>

    <div class="section">
        <h3>Browser Results</h3>
        <table>
            <thead>
                <tr>
                    <th>Browser</th>
                    <th>Total</th>
                    <th>Passed</th>
                    <th>Failed</th>
                    <th>Pass Rate</th>
                    <th>Duration</th>
                </tr>
            </thead>
            <tbody>
                ${Object.entries(this.consolidatedReport.browsers).map(([browser, data]) => `
                    <tr>
                        <td>${browser}</td>
                        <td>${data.total}</td>
                        <td style="color: #27ae60">${data.passed}</td>
                        <td style="color: #e74c3c">${data.failed}</td>
                        <td>${data.passRate.toFixed(1)}%</td>
                        <td>${Math.round(data.duration / 1000)}s</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    </div>

    <div class="section">
        <h3>Shard Performance</h3>
        <table>
            <thead>
                <tr>
                    <th>Shard</th>
                    <th>Browser</th>
                    <th>Tests</th>
                    <th>Duration</th>
                    <th>Pass Rate</th>
                </tr>
            </thead>
            <tbody>
                ${Object.entries(this.consolidatedReport.shards).map(([shardKey, data]) => `
                    <tr>
                        <td>${data.shard}</td>
                        <td>${data.browser}</td>
                        <td>${data.total}</td>
                        <td>${Math.round(data.duration / 1000)}s</td>
                        <td>${data.total > 0 ? ((data.passed / data.total) * 100).toFixed(1) : 0}%</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    </div>

    ${this.consolidatedReport.performance.slowestTests.length > 0 ? `
    <div class="section">
        <h3>Slowest Tests</h3>
        <table>
            <thead>
                <tr>
                    <th>Test</th>
                    <th>Duration</th>
                    <th>Browser</th>
                    <th>Shard</th>
                </tr>
            </thead>
            <tbody>
                ${this.consolidatedReport.performance.slowestTests.slice(0, 10).map(test => `
                    <tr>
                        <td>${test.title}</td>
                        <td>${Math.round(test.duration)}ms</td>
                        <td>${test.browser}</td>
                        <td>${test.shard}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    </div>
    ` : ''}
</body>
</html>`;
  }

  async generateJunitReport() {
    // Generate consolidated JUnit XML for CI/CD integration
    const junitPath = path.join(this.outputDir, 'consolidated-junit.xml');
    
    const xml = `<?xml version="1.0" encoding="UTF-8"?>
<testsuites name="E2E Tests" tests="${this.consolidatedReport.summary.total}" failures="${this.consolidatedReport.summary.failed}" 
           skipped="${this.consolidatedReport.summary.skipped}" time="${this.consolidatedReport.summary.duration / 1000}">
  ${Object.entries(this.consolidatedReport.suites).map(([suiteName, suite]) => `
  <testsuite name="${suiteName}" tests="${suite.total}" failures="${suite.failed}" 
             skipped="${suite.skipped}" time="${suite.duration / 1000}">
    ${this.consolidatedReport.tests
      .filter(test => test.fullTitle.startsWith(suiteName))
      .map(test => `
    <testcase name="${test.title}" classname="${suiteName}" time="${test.duration / 1000}">
      ${test.status === 'failed' ? `
      <failure message="${test.error?.message || 'Test failed'}" type="AssertionError">
        ${test.error?.stack || 'No stack trace available'}
      </failure>` : ''}
      ${test.status === 'skipped' ? '<skipped/>' : ''}
    </testcase>`).join('')}
  </testsuite>`).join('')}
</testsuites>`;

    fs.writeFileSync(junitPath, xml);
    console.log(`Consolidated JUnit report saved to: ${junitPath}`);
  }

  async generatePerformanceSummary() {
    const performanceSummary = {
      timestamp: new Date().toISOString(),
      buildNumber: this.consolidatedReport.environment.buildNumber,
      summary: {
        totalExecutionTime: this.consolidatedReport.performance.totalExecutionTime,
        averageTestDuration: this.consolidatedReport.performance.averageTestDuration,
        slowestTestCount: this.consolidatedReport.performance.slowestTests.length,
        flakyTestCount: this.consolidatedReport.summary.flaky
      },
      browser_performance: {},
      shard_performance: {}
    };

    // Calculate browser performance metrics
    Object.entries(this.consolidatedReport.browsers).forEach(([browser, data]) => {
      performanceSummary.browser_performance[browser] = {
        totalDuration: data.duration,
        averageDuration: data.total > 0 ? data.duration / data.total : 0,
        testCount: data.total
      };
    });

    // Calculate shard performance metrics
    Object.entries(this.consolidatedReport.shards).forEach(([shardKey, data]) => {
      performanceSummary.shard_performance[shardKey] = {
        totalDuration: data.duration,
        averageDuration: data.total > 0 ? data.duration / data.total : 0,
        testCount: data.total,
        efficiency: data.total > 0 ? data.duration / data.total : 0
      };
    });

    const performancePath = path.join(this.outputDir, 'performance-summary.json');
    fs.writeFileSync(performancePath, JSON.stringify(performanceSummary, null, 2));
    console.log(`Performance summary saved to: ${performancePath}`);
  }
}

// Run if called directly
if (require.main === module) {
  const consolidator = new ResultsConsolidator(process.argv[2]);
  consolidator.consolidate().catch(error => {
    console.error('Consolidation failed:', error);
    process.exit(1);
  });
}

module.exports = ResultsConsolidator;