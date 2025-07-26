const fs = require('fs');
const path = require('path');
const nodemailer = require('nodemailer');

class EmailNotifier {
  constructor() {
    this.config = {
      host: process.env.EMAIL_HOST,
      port: parseInt(process.env.EMAIL_PORT) || 587,
      secure: process.env.EMAIL_SECURE === 'true',
      auth: {
        user: process.env.EMAIL_USER,
        pass: process.env.EMAIL_PASS
      }
    };
    
    this.recipients = (process.env.EMAIL_TO || '').split(',').filter(email => email.trim());
    this.from = process.env.EMAIL_FROM || process.env.EMAIL_USER;
    
    if (!this.config.host || !this.config.auth.user || !this.config.auth.pass) {
      console.log('Email configuration incomplete, skipping email notification');
      process.exit(0);
    }
    
    if (this.recipients.length === 0) {
      console.log('No email recipients configured, skipping email notification');
      process.exit(0);
    }
  }

  async loadTestResults() {
    const resultsPath = path.join(__dirname, '../test-results/consolidated-report.json');
    
    if (!fs.existsSync(resultsPath)) {
      console.log('No consolidated test results found');
      return null;
    }

    try {
      return JSON.parse(fs.readFileSync(resultsPath, 'utf8'));
    } catch (error) {
      console.error('Error reading test results:', error.message);
      return null;
    }
  }

  generateHtmlReport(results) {
    const { summary, environment, tests, performance } = results;
    const context = JSON.parse(process.env.GITHUB_CONTEXT || '{}');
    const passRate = ((summary.passed / summary.total) * 100).toFixed(1);
    const duration = Math.round(summary.duration / 1000);
    
    // Determine status
    let statusColor = '#28a745'; // green
    let statusText = 'PASSED';
    
    if (summary.failed > 0) {
      statusColor = '#dc3545'; // red
      statusText = 'FAILED';
    } else if (summary.flaky > 0) {
      statusColor = '#ffc107'; // yellow
      statusText = 'UNSTABLE';
    }

    const failedTests = tests ? tests.filter(test => test.status === 'failed') : [];
    const flakyTests = tests ? tests.filter(test => test.status === 'passed' && test.retry > 0) : [];

    return `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E2E Test Results - ${statusText}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: ${statusColor};
            color: white;
            padding: 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .status {
            font-size: 18px;
            font-weight: bold;
            margin-top: 10px;
        }
        .content {
            padding: 20px;
        }
        .section {
            margin-bottom: 30px;
        }
        .section h2 {
            color: #495057;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .metric {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
        }
        .metric-value {
            font-size: 24px;
            font-weight: bold;
            color: #495057;
        }
        .metric-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            margin-top: 5px;
        }
        .test-list {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
            margin-top: 15px;
        }
        .test-item {
            background: white;
            margin: 5px 0;
            padding: 10px;
            border-radius: 4px;
            border-left: 4px solid #dc3545;
        }
        .test-title {
            font-weight: bold;
            color: #495057;
        }
        .test-error {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
            font-family: monospace;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
        }
        .info-item {
            display: flex;
            justify-content: space-between;
        }
        .info-label {
            font-weight: bold;
            color: #495057;
        }
        .info-value {
            color: #6c757d;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 5px;
        }
        .btn:hover {
            background: #0056b3;
        }
        .footer {
            background: #f8f9fa;
            padding: 15px 20px;
            text-align: center;
            font-size: 12px;
            color: #6c757d;
            border-top: 1px solid #e9ecef;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>E2E Test Results</h1>
            <div class="status">${statusText}</div>
            <div>Build #${environment.buildNumber || 'local'} • ${environment.gitBranch || 'unknown'}</div>
        </div>
        
        <div class="content">
            <div class="section">
                <h2>📊 Test Summary</h2>
                <div class="metrics">
                    <div class="metric">
                        <div class="metric-value">${summary.total}</div>
                        <div class="metric-label">Total Tests</div>
                    </div>
                    <div class="metric">
                        <div class="metric-value">${summary.passed}</div>
                        <div class="metric-label">Passed</div>
                    </div>
                    <div class="metric">
                        <div class="metric-value">${summary.failed}</div>
                        <div class="metric-label">Failed</div>
                    </div>
                    <div class="metric">
                        <div class="metric-value">${summary.skipped}</div>
                        <div class="metric-label">Skipped</div>
                    </div>
                    <div class="metric">
                        <div class="metric-value">${summary.flaky}</div>
                        <div class="metric-label">Flaky</div>
                    </div>
                    <div class="metric">
                        <div class="metric-value">${passRate}%</div>
                        <div class="metric-label">Pass Rate</div>
                    </div>
                    <div class="metric">
                        <div class="metric-value">${duration}s</div>
                        <div class="metric-label">Duration</div>
                    </div>
                </div>
            </div>

            <div class="section">
                <h2>ℹ️ Build Information</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Repository:</span>
                        <span class="info-value">${context.repository || 'MakeDealCRM'}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Branch:</span>
                        <span class="info-value">${environment.gitBranch || 'unknown'}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Commit:</span>
                        <span class="info-value">${environment.gitCommit?.substring(0, 8) || 'unknown'}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Build Number:</span>
                        <span class="info-value">#${environment.buildNumber || 'local'}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Started:</span>
                        <span class="info-value">${new Date(summary.startTime).toLocaleString()}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Completed:</span>
                        <span class="info-value">${new Date(summary.endTime).toLocaleString()}</span>
                    </div>
                </div>
            </div>

            ${performance ? `
            <div class="section">
                <h2>⚡ Performance Metrics</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Average Test Duration:</span>
                        <span class="info-value">${Math.round(performance.averageTestDuration)}ms</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Slowest Tests:</span>
                        <span class="info-value">${performance.slowestTests.length}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Total Execution Time:</span>
                        <span class="info-value">${Math.round(performance.totalExecutionTime / 1000)}s</span>
                    </div>
                </div>
            </div>
            ` : ''}

            ${failedTests.length > 0 ? `
            <div class="section">
                <h2>❌ Failed Tests (${failedTests.length})</h2>
                <div class="test-list">
                    ${failedTests.slice(0, 10).map(test => `
                        <div class="test-item">
                            <div class="test-title">${test.fullTitle}</div>
                            <div class="test-error">${test.error ? test.error.message : 'No error message available'}</div>
                        </div>
                    `).join('')}
                    ${failedTests.length > 10 ? `
                        <div style="text-align: center; margin-top: 15px; color: #6c757d;">
                            ... and ${failedTests.length - 10} more failed tests
                        </div>
                    ` : ''}
                </div>
            </div>
            ` : ''}

            ${flakyTests.length > 0 ? `
            <div class="section">
                <h2>⚠️ Flaky Tests (${flakyTests.length})</h2>
                <div class="test-list">
                    ${flakyTests.slice(0, 5).map(test => `
                        <div class="test-item" style="border-left-color: #ffc107;">
                            <div class="test-title">${test.fullTitle}</div>
                            <div class="test-error">Passed after ${test.retry} retries</div>
                        </div>
                    `).join('')}
                </div>
            </div>
            ` : ''}

            ${environment.buildUrl ? `
            <div class="section" style="text-align: center;">
                <a href="${environment.buildUrl}" class="btn">View Detailed Report</a>
                ${context.pull_request ? `<a href="${context.pull_request.html_url}" class="btn">View Pull Request</a>` : ''}
            </div>
            ` : ''}
        </div>
        
        <div class="footer">
            Generated by MakeDealCRM E2E Test Suite on ${new Date().toLocaleString()}
        </div>
    </div>
</body>
</html>`;
  }

  async sendEmail(results) {
    const { summary, environment } = results;
    const context = JSON.parse(process.env.GITHUB_CONTEXT || '{}');
    
    // Determine status for subject
    let status = 'PASSED';
    if (summary.failed > 0) {
      status = 'FAILED';
    } else if (summary.flaky > 0) {
      status = 'UNSTABLE';
    }

    const subject = `[${status}] E2E Tests - Build #${environment.buildNumber || 'local'} (${environment.gitBranch || 'unknown'})`;
    const htmlContent = this.generateHtmlReport(results);
    
    // Create plain text version
    const textContent = `
E2E Test Results - ${status}

Build Information:
- Repository: ${context.repository || 'MakeDealCRM'}
- Branch: ${environment.gitBranch || 'unknown'}
- Build: #${environment.buildNumber || 'local'}
- Commit: ${environment.gitCommit?.substring(0, 8) || 'unknown'}

Test Summary:
- Total Tests: ${summary.total}
- Passed: ${summary.passed}
- Failed: ${summary.failed}
- Skipped: ${summary.skipped}
- Flaky: ${summary.flaky}
- Pass Rate: ${((summary.passed / summary.total) * 100).toFixed(1)}%
- Duration: ${Math.round(summary.duration / 1000)}s

${summary.failed > 0 ? `
Failed Tests:
${results.tests?.filter(test => test.status === 'failed').slice(0, 5).map(test => `- ${test.fullTitle}`).join('\n') || 'No details available'}
` : ''}

${environment.buildUrl ? `View detailed report: ${environment.buildUrl}` : ''}
`;

    const transporter = nodemailer.createTransporter(this.config);
    
    const mailOptions = {
      from: this.from,
      to: this.recipients,
      subject: subject,
      text: textContent,
      html: htmlContent
    };

    try {
      const info = await transporter.sendMail(mailOptions);
      console.log('Email sent successfully:', info.messageId);
      return info;
    } catch (error) {
      console.error('Error sending email:', error.message);
      throw error;
    }
  }

  async notify() {
    try {
      const results = await this.loadTestResults();
      if (!results) {
        console.log('No test results to notify about');
        return;
      }

      await this.sendEmail(results);
      
    } catch (error) {
      console.error('Failed to send email notification:', error.message);
      process.exit(1);
    }
  }
}

// Run if called directly
if (require.main === module) {
  const notifier = new EmailNotifier();
  notifier.notify();
}

module.exports = EmailNotifier;