/**
 * Page Object Model exports
 * Central export file for all page objects
 */

const BasePage = require('./BasePage');
const LoginPage = require('./LoginPage');
const DealPage = require('./DealPage');
const ContactPage = require('./ContactPage');
const DocumentPage = require('./DocumentPage');
const ChecklistPage = require('./ChecklistPage');
const PipelinePage = require('./PipelinePage');
const NavigationComponent = require('./components/NavigationComponent');

module.exports = {
  BasePage,
  LoginPage,
  DealPage,
  ContactPage,
  DocumentPage,
  ChecklistPage,
  PipelinePage,
  NavigationComponent
};