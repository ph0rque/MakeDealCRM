const fs = require('fs');
const path = require('path');

class TrendAnalyzer {
  constructor() {
    this.outputDir = path.resolve(__dirname, '../test-results');
    this.historyDir = path.resolve(__dirname, '../test-history');
    this.trendsFile = path.join(this.outputDir, 'trend-analysis.json');
    this.maxHistoryDays = 90; // Keep 90 days of history
  }

  async analyze() {
    console.log('Starting trend analysis...');
    
    // Ensure directories exist
    [this.outputDir, this.historyDir].forEach(dir => {
      if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
      }
    });

    // Load current results
    const currentResults = await this.loadCurrentResults();
    if (!currentResults) {
      console.log('No current test results found for trend analysis');
      return;
    }

    // Save current results to history
    await this.saveToHistory(currentResults);

    // Load historical data
    const historicalData = await this.loadHistoricalData();

    // Perform trend analysis
    const trends = await this.performTrendAnalysis(historicalData);

    // Generate trend report
    await this.generateTrendReport(trends);

    console.log('Trend analysis completed');
  }

  async loadCurrentResults() {
    const resultsPath = path.join(this.outputDir, 'consolidated-report.json');
    
    if (!fs.existsSync(resultsPath)) {
      return null;
    }

    try {
      return JSON.parse(fs.readFileSync(resultsPath, 'utf8'));
    } catch (error) {
      console.error('Error loading current results:', error.message);
      return null;
    }
  }

  async saveToHistory(results) {
    const timestamp = new Date().toISOString().split('T')[0]; // YYYY-MM-DD
    const buildNumber = results.environment.buildNumber || 'unknown';
    const filename = `${timestamp}-${buildNumber}.json`;
    const filepath = path.join(this.historyDir, filename);

    // Create a simplified version for history
    const historyEntry = {
      timestamp: new Date().toISOString(),
      date: timestamp,
      buildNumber: buildNumber,
      gitCommit: results.environment.gitCommit,
      gitBranch: results.environment.gitBranch,
      summary: results.summary,
      performance: {
        averageTestDuration: results.performance.averageTestDuration,
        totalExecutionTime: results.performance.totalExecutionTime,
        slowTestCount: results.performance.slowestTests.length
      },
      browsers: results.browsers,
      flakyTests: results.tests ? results.tests.filter(test => 
        test.status === 'passed' && test.retry > 0
      ).map(test => ({
        title: test.fullTitle,
        retries: test.retry,
        browser: test.browser
      })) : [],
      failedTests: results.tests ? results.tests.filter(test => 
        test.status === 'failed'
      ).map(test => ({
        title: test.fullTitle,
        error: test.error?.message || 'Unknown error',
        browser: test.browser
      })) : []
    };

    fs.writeFileSync(filepath, JSON.stringify(historyEntry, null, 2));
    console.log(`Saved test results to history: ${filename}`);
  }

  async loadHistoricalData() {
    if (!fs.existsSync(this.historyDir)) {
      return [];
    }

    const files = fs.readdirSync(this.historyDir)
      .filter(file => file.endsWith('.json'))
      .sort()
      .reverse(); // Most recent first

    const cutoffDate = new Date();
    cutoffDate.setDate(cutoffDate.getDate() - this.maxHistoryDays);

    const historicalData = [];

    for (const file of files) {
      try {
        const filePath = path.join(this.historyDir, file);
        const data = JSON.parse(fs.readFileSync(filePath, 'utf8'));
        
        // Skip if older than retention period
        if (new Date(data.timestamp) < cutoffDate) {
          // Optionally delete old files
          fs.unlinkSync(filePath);
          continue;
        }

        historicalData.push(data);
      } catch (error) {
        console.error(`Error loading history file ${file}:`, error.message);
      }
    }

    console.log(`Loaded ${historicalData.length} historical data points`);
    return historicalData.reverse(); // Chronological order
  }

  async performTrendAnalysis(historicalData) {
    if (historicalData.length < 2) {
      console.log('Insufficient historical data for trend analysis');
      return {
        summary: 'Insufficient data',
        trends: {},
        insights: []
      };
    }

    const trends = {
      passRate: this.analyzeTrend(historicalData, data => 
        (data.summary.passed / data.summary.total) * 100
      ),
      duration: this.analyzeTrend(historicalData, data => 
        data.performance.totalExecutionTime / 1000
      ),
      averageTestDuration: this.analyzeTrend(historicalData, data => 
        data.performance.averageTestDuration
      ),
      flakyTestCount: this.analyzeTrend(historicalData, data => 
        data.flakyTests.length
      ),
      failureCount: this.analyzeTrend(historicalData, data => 
        data.summary.failed
      ),
      testCount: this.analyzeTrend(historicalData, data => 
        data.summary.total
      )
    };

    // Browser-specific trends
    const browsers = this.extractUniqueBrowsers(historicalData);
    trends.browsers = {};
    
    browsers.forEach(browser => {
      trends.browsers[browser] = {
        passRate: this.analyzeTrend(historicalData, data => 
          data.browsers[browser] ? 
            (data.browsers[browser].passed / data.browsers[browser].total) * 100 : null
        ),
        duration: this.analyzeTrend(historicalData, data => 
          data.browsers[browser] ? data.browsers[browser].duration / 1000 : null
        )
      };
    });

    // Generate insights
    const insights = this.generateInsights(trends, historicalData);

    return {
      summary: `Analysis of ${historicalData.length} builds over ${this.calculateDaysBetween(historicalData[0].timestamp, historicalData[historicalData.length - 1].timestamp)} days`,
      dataPoints: historicalData.length,
      dateRange: {
        start: historicalData[0].date,
        end: historicalData[historicalData.length - 1].date
      },
      trends,
      insights,
      flakyTestPatterns: this.analyzeFlakyTestPatterns(historicalData),
      failurePatterns: this.analyzeFailurePatterns(historicalData)
    };
  }

  analyzeTrend(data, extractor) {
    const values = data.map(extractor).filter(v => v !== null && v !== undefined);
    
    if (values.length < 2) {
      return { trend: 'insufficient_data', values: values };
    }

    // Calculate trend direction
    const first = values[0];
    const last = values[values.length - 1];
    const change = last - first;
    const percentChange = first !== 0 ? (change / first) * 100 : 0;

    // Calculate moving average for smoothing
    const windowSize = Math.min(5, Math.floor(values.length / 2));
    const movingAverage = this.calculateMovingAverage(values, windowSize);

    // Determine trend direction
    let trend = 'stable';
    if (Math.abs(percentChange) > 5) {
      trend = percentChange > 0 ? 'increasing' : 'decreasing';
    }

    // Calculate volatility (standard deviation)
    const mean = values.reduce((sum, v) => sum + v, 0) / values.length;
    const variance = values.reduce((sum, v) => sum + Math.pow(v - mean, 2), 0) / values.length;
    const volatility = Math.sqrt(variance);

    return {
      trend,
      current: last,
      previous: first,
      change,
      percentChange: parseFloat(percentChange.toFixed(2)),
      average: parseFloat(mean.toFixed(2)),
      volatility: parseFloat(volatility.toFixed(2)),
      values,
      movingAverage
    };
  }

  calculateMovingAverage(values, windowSize) {
    const result = [];
    for (let i = windowSize - 1; i < values.length; i++) {
      const window = values.slice(i - windowSize + 1, i + 1);
      const avg = window.reduce((sum, v) => sum + v, 0) / window.length;
      result.push(parseFloat(avg.toFixed(2)));
    }
    return result;
  }

  extractUniqueBrowsers(historicalData) {
    const browsers = new Set();
    historicalData.forEach(data => {
      if (data.browsers) {
        Object.keys(data.browsers).forEach(browser => browsers.add(browser));
      }
    });
    return Array.from(browsers);
  }

  generateInsights(trends, historicalData) {
    const insights = [];

    // Pass rate insights
    if (trends.passRate.trend === 'decreasing' && trends.passRate.percentChange < -10) {
      insights.push({
        type: 'warning',
        category: 'quality',
        message: `Pass rate has decreased by ${Math.abs(trends.passRate.percentChange)}% over time`,
        recommendation: 'Investigate recent changes and address failing tests'
      });
    } else if (trends.passRate.trend === 'increasing' && trends.passRate.percentChange > 5) {
      insights.push({
        type: 'positive',
        category: 'quality',
        message: `Pass rate has improved by ${trends.passRate.percentChange}% over time`,
        recommendation: 'Continue current testing practices'
      });
    }

    // Performance insights
    if (trends.duration.trend === 'increasing' && trends.duration.percentChange > 20) {
      insights.push({
        type: 'warning',
        category: 'performance',
        message: `Test execution time has increased by ${trends.duration.percentChange}%`,
        recommendation: 'Review and optimize slow tests or infrastructure'
      });
    }

    // Flaky test insights
    if (trends.flakyTestCount.trend === 'increasing') {
      insights.push({
        type: 'warning',
        category: 'stability',
        message: `Flaky test count is increasing (${trends.flakyTestCount.change} more flaky tests)`,
        recommendation: 'Prioritize fixing flaky tests to improve suite reliability'
      });
    }

    // Test count insights
    if (trends.testCount.percentChange > 25) {
      insights.push({
        type: 'info',
        category: 'coverage',
        message: `Test count has increased by ${trends.testCount.percentChange}%`,
        recommendation: 'Monitor test execution time as test count grows'
      });
    }

    // Volatility insights
    if (trends.passRate.volatility > 10) {
      insights.push({
        type: 'warning',
        category: 'stability',
        message: 'Pass rate shows high volatility, indicating inconsistent test results',
        recommendation: 'Investigate environmental factors or flaky tests'
      });
    }

    return insights;
  }

  analyzeFlakyTestPatterns(historicalData) {
    const flakyTests = {};
    
    historicalData.forEach(data => {
      data.flakyTests.forEach(test => {
        if (!flakyTests[test.title]) {
          flakyTests[test.title] = {
            occurrences: 0,
            totalRetries: 0,
            browsers: new Set(),
            firstSeen: data.date,
            lastSeen: data.date
          };
        }
        
        flakyTests[test.title].occurrences++;
        flakyTests[test.title].totalRetries += test.retries;
        flakyTests[test.title].browsers.add(test.browser);
        flakyTests[test.title].lastSeen = data.date;
      });
    });

    // Convert to array and sort by frequency
    const patterns = Object.entries(flakyTests)
      .map(([title, data]) => ({
        title,
        occurrences: data.occurrences,
        frequency: (data.occurrences / historicalData.length) * 100,
        averageRetries: data.totalRetries / data.occurrences,
        browsers: Array.from(data.browsers),
        firstSeen: data.firstSeen,
        lastSeen: data.lastSeen
      }))
      .sort((a, b) => b.frequency - a.frequency)
      .slice(0, 10); // Top 10 most frequent flaky tests

    return patterns;
  }

  analyzeFailurePatterns(historicalData) {
    const failedTests = {};
    
    historicalData.forEach(data => {
      data.failedTests.forEach(test => {
        if (!failedTests[test.title]) {
          failedTests[test.title] = {
            occurrences: 0,
            browsers: new Set(),
            errors: new Set(),
            firstSeen: data.date,
            lastSeen: data.date
          };
        }
        
        failedTests[test.title].occurrences++;
        failedTests[test.title].browsers.add(test.browser);
        failedTests[test.title].errors.add(test.error);
        failedTests[test.title].lastSeen = data.date;
      });
    });

    // Convert to array and sort by frequency
    const patterns = Object.entries(failedTests)
      .map(([title, data]) => ({
        title,
        occurrences: data.occurrences,
        frequency: (data.occurrences / historicalData.length) * 100,
        browsers: Array.from(data.browsers),
        errors: Array.from(data.errors),
        firstSeen: data.firstSeen,
        lastSeen: data.lastSeen
      }))
      .sort((a, b) => b.frequency - a.frequency)
      .slice(0, 10); // Top 10 most frequent failing tests

    return patterns;
  }

  calculateDaysBetween(date1, date2) {
    const d1 = new Date(date1);
    const d2 = new Date(date2);
    const diffTime = Math.abs(d2 - d1);
    return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
  }

  async generateTrendReport(trends) {
    // Save JSON report
    fs.writeFileSync(this.trendsFile, JSON.stringify(trends, null, 2));
    
    // Generate HTML report
    const htmlPath = path.join(this.outputDir, 'trend-analysis.html');
    const htmlContent = this.generateTrendHtml(trends);
    fs.writeFileSync(htmlPath, htmlContent);
    
    console.log(`Trend analysis saved to: ${this.trendsFile}`);
    console.log(`Trend HTML report saved to: ${htmlPath}`);
  }

  generateTrendHtml(trends) {
    return `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E2E Test Trend Analysis</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .header { background: #16a085; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .summary { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .trends { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 20px; }
        .trend-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .trend-value { font-size: 2em; font-weight: bold; }
        .trend-positive { color: #27ae60; }
        .trend-negative { color: #e74c3c; }
        .trend-stable { color: #f39c12; }
        .insights { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .insight { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .insight.warning { background: #fff3e0; border-left: 4px solid #ff9800; }
        .insight.positive { background: #e8f5e8; border-left: 4px solid #4caf50; }
        .insight.info { background: #e3f2fd; border-left: 4px solid #2196f3; }
        .patterns { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; }
        .chart-container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>E2E Test Trend Analysis</h1>
        <p>${trends.summary}</p>
        <p>Analysis period: ${trends.dateRange ? `${trends.dateRange.start} to ${trends.dateRange.end}` : 'N/A'}</p>
    </div>

    <div class="summary">
        <h3>📊 Trend Summary</h3>
        <p><strong>Data Points:</strong> ${trends.dataPoints}</p>
        <p><strong>Analysis Period:</strong> ${trends.dateRange ? this.calculateDaysBetween(trends.dateRange.start, trends.dateRange.end) : 0} days</p>
    </div>

    <div class="trends">
        <div class="trend-card">
            <h4>Pass Rate Trend</h4>
            <div class="trend-value trend-${trends.trends.passRate.trend === 'increasing' ? 'positive' : trends.trends.passRate.trend === 'decreasing' ? 'negative' : 'stable'}">
                ${trends.trends.passRate.current ? trends.trends.passRate.current.toFixed(1) : 'N/A'}%
            </div>
            <p>${trends.trends.passRate.percentChange > 0 ? '+' : ''}${trends.trends.passRate.percentChange}% from baseline</p>
        </div>
        
        <div class="trend-card">
            <h4>Execution Time Trend</h4>
            <div class="trend-value trend-${trends.trends.duration.trend === 'decreasing' ? 'positive' : trends.trends.duration.trend === 'increasing' ? 'negative' : 'stable'}">
                ${trends.trends.duration.current ? Math.round(trends.trends.duration.current) : 'N/A'}s
            </div>
            <p>${trends.trends.duration.percentChange > 0 ? '+' : ''}${trends.trends.duration.percentChange}% from baseline</p>
        </div>
        
        <div class="trend-card">
            <h4>Flaky Tests Trend</h4>
            <div class="trend-value trend-${trends.trends.flakyTestCount.trend === 'decreasing' ? 'positive' : trends.trends.flakyTestCount.trend === 'increasing' ? 'negative' : 'stable'}">
                ${trends.trends.flakyTestCount.current || 0}
            </div>
            <p>${trends.trends.flakyTestCount.change > 0 ? '+' : ''}${trends.trends.flakyTestCount.change} from baseline</p>
        </div>
        
        <div class="trend-card">
            <h4>Test Count Trend</h4>
            <div class="trend-value trend-positive">
                ${trends.trends.testCount.current || 0}
            </div>
            <p>${trends.trends.testCount.percentChange > 0 ? '+' : ''}${trends.trends.testCount.percentChange}% from baseline</p>
        </div>
    </div>

    <div class="insights">
        <h3>💡 Insights & Recommendations</h3>
        ${trends.insights.map(insight => `
            <div class="insight ${insight.type}">
                <strong>${insight.message}</strong><br>
                <em>Recommendation: ${insight.recommendation}</em>
            </div>
        `).join('')}
        ${trends.insights.length === 0 ? '<p>No significant trends detected.</p>' : ''}
    </div>

    ${trends.flakyTestPatterns && trends.flakyTestPatterns.length > 0 ? `
    <div class="patterns">
        <h3>🔄 Most Frequent Flaky Tests</h3>
        <table>
            <thead>
                <tr>
                    <th>Test</th>
                    <th>Frequency</th>
                    <th>Avg Retries</th>
                    <th>Browsers</th>
                    <th>Last Seen</th>
                </tr>
            </thead>
            <tbody>
                ${trends.flakyTestPatterns.slice(0, 5).map(pattern => `
                    <tr>
                        <td>${pattern.title}</td>
                        <td>${pattern.frequency.toFixed(1)}%</td>
                        <td>${pattern.averageRetries.toFixed(1)}</td>
                        <td>${pattern.browsers.join(', ')}</td>
                        <td>${pattern.lastSeen}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    </div>
    ` : ''}

    ${trends.failurePatterns && trends.failurePatterns.length > 0 ? `
    <div class="patterns">
        <h3>❌ Most Frequent Test Failures</h3>
        <table>
            <thead>
                <tr>
                    <th>Test</th>
                    <th>Frequency</th>
                    <th>Browsers</th>
                    <th>Last Seen</th>
                </tr>
            </thead>
            <tbody>
                ${trends.failurePatterns.slice(0, 5).map(pattern => `
                    <tr>
                        <td>${pattern.title}</td>
                        <td>${pattern.frequency.toFixed(1)}%</td>
                        <td>${pattern.browsers.join(', ')}</td>
                        <td>${pattern.lastSeen}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    </div>
    ` : ''}

    <script>
        // You can add chart.js visualizations here for trend data
        console.log('Trend data:', ${JSON.stringify(trends, null, 2)});
    </script>
</body>
</html>`;
  }
}

// Run if called directly
if (require.main === module) {
  const analyzer = new TrendAnalyzer();
  analyzer.analyze().catch(error => {
    console.error('Trend analysis failed:', error);
    process.exit(1);
  });
}

module.exports = TrendAnalyzer;