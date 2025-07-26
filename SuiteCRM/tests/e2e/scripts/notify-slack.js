const fs = require('fs');
const https = require('https');
const path = require('path');

class SlackNotifier {
  constructor() {
    this.webhookUrl = process.env.SLACK_WEBHOOK_URL;
    this.channel = process.env.SLACK_CHANNEL || '#e2e-tests';
    this.username = process.env.SLACK_USERNAME || 'E2E Test Bot';
    this.iconEmoji = process.env.SLACK_ICON || ':robot_face:';
    
    if (!this.webhookUrl) {
      console.log('SLACK_WEBHOOK_URL not configured, skipping Slack notification');
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

  buildMessage(results) {
    const context = JSON.parse(process.env.GITHUB_CONTEXT || '{}');
    const { summary, environment } = results;
    const passRate = ((summary.passed / summary.total) * 100).toFixed(1);
    const duration = Math.round(summary.duration / 1000);
    
    // Determine overall status
    let status = 'success';
    let statusEmoji = ':white_check_mark:';
    let statusColor = 'good';
    
    if (summary.failed > 0) {
      status = 'failure';
      statusEmoji = ':x:';
      statusColor = 'danger';
    } else if (summary.flaky > 0) {
      status = 'warning';
      statusEmoji = ':warning:';
      statusColor = 'warning';
    }

    const blocks = [
      {
        type: 'header',
        text: {
          type: 'plain_text',
          text: `${statusEmoji} E2E Test Results - ${status.toUpperCase()}`
        }
      },
      {
        type: 'section',
        fields: [
          {
            type: 'mrkdwn',
            text: `*Repository:*\n${context.repository || 'MakeDealCRM'}`
          },
          {
            type: 'mrkdwn',
            text: `*Branch:*\n${environment.gitBranch || 'unknown'}`
          },
          {
            type: 'mrkdwn',
            text: `*Build:*\n#${environment.buildNumber || 'local'}`
          },
          {
            type: 'mrkdwn',
            text: `*Commit:*\n${environment.gitCommit?.substring(0, 8) || 'unknown'}`
          }
        ]
      },
      {
        type: 'section',
        text: {
          type: 'mrkdwn',
          text: `*Test Summary:*`
        }
      },
      {
        type: 'section',
        fields: [
          {
            type: 'mrkdwn',
            text: `*Total Tests:* ${summary.total}`
          },
          {
            type: 'mrkdwn',
            text: `*Pass Rate:* ${passRate}%`
          },
          {
            type: 'mrkdwn',
            text: `*:white_check_mark: Passed:* ${summary.passed}`
          },
          {
            type: 'mrkdwn',
            text: `*:x: Failed:* ${summary.failed}`
          },
          {
            type: 'mrkdwn',
            text: `*:fast_forward: Skipped:* ${summary.skipped}`
          },
          {
            type: 'mrkdwn',
            text: `*:repeat: Flaky:* ${summary.flaky}`
          },
          {
            type: 'mrkdwn',
            text: `*:stopwatch: Duration:* ${duration}s`
          },
          {
            type: 'mrkdwn',
            text: `*:calendar: Started:* ${new Date(summary.startTime).toLocaleString()}`
          }
        ]
      }
    ];

    // Add failed tests section if there are failures
    if (summary.failed > 0 && results.tests) {
      const failedTests = results.tests
        .filter(test => test.status === 'failed')
        .slice(0, 5) // Show only first 5 failed tests
        .map(test => `• ${test.fullTitle}`)
        .join('\n');

      blocks.push({
        type: 'section',
        text: {
          type: 'mrkdwn',
          text: `*:x: Failed Tests (showing first 5):*\n\`\`\`${failedTests}\`\`\``
        }
      });

      if (results.tests.filter(test => test.status === 'failed').length > 5) {
        blocks.push({
          type: 'context',
          elements: [{
            type: 'mrkdwn',
            text: `... and ${results.tests.filter(test => test.status === 'failed').length - 5} more failed tests`
          }]
        });
      }
    }

    // Add flaky tests section if there are any
    if (summary.flaky > 0 && results.tests) {
      const flakyTests = results.tests
        .filter(test => test.status === 'passed' && test.retry > 0)
        .slice(0, 3)
        .map(test => `• ${test.fullTitle} (${test.retry} retries)`)
        .join('\n');

      blocks.push({
        type: 'section',
        text: {
          type: 'mrkdwn',
          text: `*:warning: Flaky Tests:*\n\`\`\`${flakyTests}\`\`\``
        }
      });
    }

    // Add performance insights if available
    if (results.performance) {
      const avgDuration = Math.round(results.performance.averageTestDuration);
      const slowTests = results.performance.slowestTests.length;
      
      blocks.push({
        type: 'section',
        text: {
          type: 'mrkdwn',
          text: `*:chart_with_upwards_trend: Performance:*\nAvg test duration: ${avgDuration}ms | Slow tests: ${slowTests}`
        }
      });
    }

    // Add action buttons
    const actions = {
      type: 'actions',
      elements: []
    };

    if (environment.buildUrl) {
      actions.elements.push({
        type: 'button',
        text: {
          type: 'plain_text',
          text: 'View Build Log'
        },
        url: environment.buildUrl,
        style: 'primary'
      });
    }

    if (context.pull_request) {
      actions.elements.push({
        type: 'button',
        text: {
          type: 'plain_text',
          text: 'View PR'
        },
        url: context.pull_request.html_url
      });
    }

    if (actions.elements.length > 0) {
      blocks.push(actions);
    }

    return {
      channel: this.channel,
      username: this.username,
      icon_emoji: this.iconEmoji,
      attachments: [{
        color: statusColor,
        blocks: blocks
      }]
    };
  }

  async sendNotification(message) {
    return new Promise((resolve, reject) => {
      const data = JSON.stringify(message);
      const url = new URL(this.webhookUrl);
      
      const options = {
        hostname: url.hostname,
        port: url.port || 443,
        path: url.pathname + url.search,
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Content-Length': data.length
        }
      };

      const req = https.request(options, (res) => {
        let responseData = '';
        
        res.on('data', chunk => {
          responseData += chunk;
        });
        
        res.on('end', () => {
          if (res.statusCode === 200) {
            console.log('Slack notification sent successfully');
            resolve(responseData);
          } else {
            console.error(`Slack notification failed with status ${res.statusCode}: ${responseData}`);
            reject(new Error(`HTTP ${res.statusCode}: ${responseData}`));
          }
        });
      });

      req.on('error', (error) => {
        console.error('Error sending Slack notification:', error.message);
        reject(error);
      });

      req.write(data);
      req.end();
    });
  }

  async notify() {
    try {
      const results = await this.loadTestResults();
      if (!results) {
        console.log('No test results to notify about');
        return;
      }

      const message = this.buildMessage(results);
      await this.sendNotification(message);
      
    } catch (error) {
      console.error('Failed to send Slack notification:', error.message);
      process.exit(1);
    }
  }
}

// Run if called directly
if (require.main === module) {
  const notifier = new SlackNotifier();
  notifier.notify();
}

module.exports = SlackNotifier;