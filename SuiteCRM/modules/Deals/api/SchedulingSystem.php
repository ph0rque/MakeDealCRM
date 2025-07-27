<?php
/**
 * Scheduling System for Task Generation Engine
 * 
 * Calculates due dates, handles business day calculations, manages
 * time zones, and provides flexible scheduling options for generated tasks.
 * 
 * @category  API
 * @package   Deals
 * @author    MakeDealCRM
 * @license   GPL-3.0+
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

class SchedulingSystem
{
    private $logger;
    private $defaultTimeZone = 'UTC';
    private $businessDays = array(1, 2, 3, 4, 5); // Monday through Friday
    private $holidays = array(); // Will be loaded from configuration
    
    public function __construct()
    {
        global $log;
        $this->logger = $log;
        $this->loadHolidays();
    }
    
    /**
     * Calculate schedules for all tasks in template
     * 
     * @param array $tasks Array of task templates
     * @param array $dealData Deal data for context
     * @param array $options Scheduling options
     * @return array Tasks with calculated schedules
     */
    public function calculateSchedules($tasks, $dealData, $options = array())
    {
        try {
            $this->logger->info("SchedulingSystem: Calculating schedules for " . count($tasks) . " tasks");
            
            $baseDate = $this->getBaseDate($dealData, $options);
            $timeZone = $this->getTimeZone($dealData, $options);
            $scheduledTasks = array();
            
            foreach ($tasks as $task) {
                $scheduledTask = $this->calculateTaskSchedule($task, $baseDate, $timeZone, $dealData, $options);
                $scheduledTasks[] = $scheduledTask;
            }
            
            // Sort tasks by due date
            usort($scheduledTasks, function($a, $b) {
                return strtotime($a['due_date']) - strtotime($b['due_date']);
            });
            
            $this->logger->info("SchedulingSystem: Successfully calculated schedules");
            
            return $scheduledTasks;
            
        } catch (Exception $e) {
            $this->logger->error("SchedulingSystem: Error calculating schedules - " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Calculate schedule for individual task
     * 
     * @param array $task Task template
     * @param DateTime $baseDate Base date for calculations
     * @param string $timeZone Time zone
     * @param array $dealData Deal data
     * @param array $options Scheduling options
     * @return array Task with schedule information
     */
    private function calculateTaskSchedule($task, $baseDate, $timeZone, $dealData, $options)
    {
        $scheduledTask = $task;
        
        // Get scheduling configuration from task
        $scheduleConfig = $task['schedule'] ?? array();
        
        // Calculate due date
        $dueDate = $this->calculateDueDate($scheduleConfig, $baseDate, $timeZone, $dealData);
        $scheduledTask['due_date'] = $dueDate->format('Y-m-d H:i:s');
        
        // Calculate start date if specified
        if (isset($scheduleConfig['start_offset'])) {
            $startDate = $this->calculateStartDate($scheduleConfig, $dueDate, $timeZone);
            $scheduledTask['start_date'] = $startDate->format('Y-m-d H:i:s');
        }
        
        // Add reminder dates
        if (isset($scheduleConfig['reminders'])) {
            $scheduledTask['reminders'] = $this->calculateReminderDates(
                $scheduleConfig['reminders'],
                $dueDate,
                $timeZone
            );
        }
        
        // Add scheduling metadata
        $scheduledTask['schedule_metadata'] = array(
            'base_date' => $baseDate->format('Y-m-d H:i:s'),
            'time_zone' => $timeZone,
            'business_days_only' => $scheduleConfig['business_days_only'] ?? false,
            'exclude_holidays' => $scheduleConfig['exclude_holidays'] ?? false,
            'calculated_at' => date('Y-m-d H:i:s')
        );
        
        return $scheduledTask;
    }
    
    /**
     * Calculate due date based on schedule configuration
     * 
     * @param array $scheduleConfig Schedule configuration
     * @param DateTime $baseDate Base date
     * @param string $timeZone Time zone
     * @param array $dealData Deal data
     * @return DateTime Due date
     */
    private function calculateDueDate($scheduleConfig, $baseDate, $timeZone, $dealData)
    {
        $dueDate = clone $baseDate;
        $dueDate->setTimezone(new DateTimeZone($timeZone));
        
        // Handle different due date calculation methods
        $method = $scheduleConfig['method'] ?? 'offset';
        
        switch ($method) {
            case 'offset':
                $dueDate = $this->applyOffset($dueDate, $scheduleConfig);
                break;
                
            case 'absolute':
                $dueDate = $this->setAbsoluteDate($scheduleConfig, $timeZone);
                break;
                
            case 'relative_to_field':
                $dueDate = $this->calculateRelativeToField($scheduleConfig, $dealData, $timeZone);
                break;
                
            case 'milestone':
                $dueDate = $this->calculateMilestoneDate($scheduleConfig, $dealData, $timeZone);
                break;
                
            default:
                $dueDate = $this->applyOffset($dueDate, $scheduleConfig);
        }
        
        // Apply business day constraints
        if ($scheduleConfig['business_days_only'] ?? false) {
            $dueDate = $this->adjustToBusinessDay($dueDate);
        }
        
        // Exclude holidays if specified
        if ($scheduleConfig['exclude_holidays'] ?? false) {
            $dueDate = $this->adjustForHolidays($dueDate);
        }
        
        // Set specific time if provided
        if (isset($scheduleConfig['time'])) {
            $time = $scheduleConfig['time'];
            if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $time, $matches)) {
                $hour = (int)$matches[1];
                $minute = (int)$matches[2];
                $second = isset($matches[3]) ? (int)$matches[3] : 0;
                
                $dueDate->setTime($hour, $minute, $second);
            }
        }
        
        return $dueDate;
    }
    
    /**
     * Apply time offset to date
     * 
     * @param DateTime $date Base date
     * @param array $scheduleConfig Schedule configuration
     * @return DateTime Modified date
     */
    private function applyOffset($date, $scheduleConfig)
    {
        $offset = $scheduleConfig['offset'] ?? '+1 day';
        $businessDaysOnly = $scheduleConfig['business_days_only'] ?? false;
        
        if ($businessDaysOnly && preg_match('/^([+-]?\d+)\s+(business\s+days?|weekdays?)$/i', $offset, $matches)) {
            $days = (int)$matches[1];
            return $this->addBusinessDays($date, $days);
        }
        
        $date->modify($offset);
        return $date;
    }
    
    /**
     * Set absolute date from configuration
     * 
     * @param array $scheduleConfig Schedule configuration
     * @param string $timeZone Time zone
     * @return DateTime Absolute date
     */
    private function setAbsoluteDate($scheduleConfig, $timeZone)
    {
        $dateString = $scheduleConfig['date'] ?? 'now';
        $date = new DateTime($dateString, new DateTimeZone($timeZone));
        
        return $date;
    }
    
    /**
     * Calculate date relative to a deal field
     * 
     * @param array $scheduleConfig Schedule configuration
     * @param array $dealData Deal data
     * @param string $timeZone Time zone
     * @return DateTime Calculated date
     */
    private function calculateRelativeToField($scheduleConfig, $dealData, $timeZone)
    {
        $field = $scheduleConfig['field'];
        $offset = $scheduleConfig['offset'] ?? '0 days';
        
        $fieldValue = $dealData[$field] ?? null;
        if (!$fieldValue) {
            throw new Exception("Field '{$field}' not found in deal data for relative date calculation");
        }
        
        $baseDate = new DateTime($fieldValue, new DateTimeZone($timeZone));
        $baseDate->modify($offset);
        
        return $baseDate;
    }
    
    /**
     * Calculate milestone-based date
     * 
     * @param array $scheduleConfig Schedule configuration
     * @param array $dealData Deal data
     * @param string $timeZone Time zone
     * @return DateTime Milestone date
     */
    private function calculateMilestoneDate($scheduleConfig, $dealData, $timeZone)
    {
        $milestone = $scheduleConfig['milestone'];
        $offset = $scheduleConfig['offset'] ?? '0 days';
        
        // Define common deal milestones
        $milestones = array(
            'deal_start' => $dealData['date_entered'],
            'deal_close' => $dealData['deal_close_date'],
            'stage_entered' => $dealData['stage_entered_date_c'] ?? $dealData['date_entered']
        );
        
        if (!isset($milestones[$milestone])) {
            throw new Exception("Unknown milestone: {$milestone}");
        }
        
        $milestoneDate = new DateTime($milestones[$milestone], new DateTimeZone($timeZone));
        $milestoneDate->modify($offset);
        
        return $milestoneDate;
    }
    
    /**
     * Calculate start date from due date
     * 
     * @param array $scheduleConfig Schedule configuration
     * @param DateTime $dueDate Due date
     * @param string $timeZone Time zone
     * @return DateTime Start date
     */
    private function calculateStartDate($scheduleConfig, $dueDate, $timeZone)
    {
        $startDate = clone $dueDate;
        $startOffset = $scheduleConfig['start_offset'];
        
        // Apply offset backwards from due date
        if (preg_match('/^-/', $startOffset)) {
            $startDate->modify($startOffset);
        } else {
            $startDate->modify('-' . $startOffset);
        }
        
        return $startDate;
    }
    
    /**
     * Calculate reminder dates
     * 
     * @param array $reminders Reminder configuration
     * @param DateTime $dueDate Due date
     * @param string $timeZone Time zone
     * @return array Reminder dates
     */
    private function calculateReminderDates($reminders, $dueDate, $timeZone)
    {
        $reminderDates = array();
        
        foreach ($reminders as $reminder) {
            $reminderDate = clone $dueDate;
            $offset = $reminder['offset'] ?? '-1 day';
            
            // Apply offset backwards from due date
            if (!preg_match('/^-/', $offset)) {
                $offset = '-' . $offset;
            }
            
            $reminderDate->modify($offset);
            
            $reminderDates[] = array(
                'date' => $reminderDate->format('Y-m-d H:i:s'),
                'type' => $reminder['type'] ?? 'email',
                'message' => $reminder['message'] ?? 'Task reminder',
                'recipients' => $reminder['recipients'] ?? array()
            );
        }
        
        return $reminderDates;
    }
    
    /**
     * Add business days to date
     * 
     * @param DateTime $date Base date
     * @param int $days Number of business days to add
     * @return DateTime Modified date
     */
    private function addBusinessDays($date, $days)
    {
        $direction = $days >= 0 ? 1 : -1;
        $days = abs($days);
        
        while ($days > 0) {
            $date->modify(($direction > 0 ? '+' : '-') . '1 day');
            
            // Check if it's a business day
            if (in_array($date->format('N'), $this->businessDays) && !$this->isHoliday($date)) {
                $days--;
            }
        }
        
        return $date;
    }
    
    /**
     * Adjust date to next business day if current date is not a business day
     * 
     * @param DateTime $date Date to adjust
     * @return DateTime Adjusted date
     */
    private function adjustToBusinessDay($date)
    {
        while (!in_array($date->format('N'), $this->businessDays) || $this->isHoliday($date)) {
            $date->modify('+1 day');
        }
        
        return $date;
    }
    
    /**
     * Adjust date to skip holidays
     * 
     * @param DateTime $date Date to adjust
     * @return DateTime Adjusted date
     */
    private function adjustForHolidays($date)
    {
        while ($this->isHoliday($date)) {
            $date->modify('+1 day');
        }
        
        return $date;
    }
    
    /**
     * Check if date is a holiday
     * 
     * @param DateTime $date Date to check
     * @return bool Whether date is a holiday
     */
    private function isHoliday($date)
    {
        $dateString = $date->format('Y-m-d');
        $monthDay = $date->format('m-d');
        
        // Check exact date holidays
        if (in_array($dateString, $this->holidays['exact'] ?? array())) {
            return true;
        }
        
        // Check recurring holidays (same month/day each year)
        if (in_array($monthDay, $this->holidays['recurring'] ?? array())) {
            return true;
        }
        
        // Check calculated holidays (e.g., Easter, Thanksgiving)
        return $this->isCalculatedHoliday($date);
    }
    
    /**
     * Check if date is a calculated holiday
     * 
     * @param DateTime $date Date to check
     * @return bool Whether date is a calculated holiday
     */
    private function isCalculatedHoliday($date)
    {
        $year = (int)$date->format('Y');
        $monthDay = $date->format('m-d');
        
        // US holidays that change dates
        $calculatedHolidays = array(
            // Memorial Day (last Monday in May)
            $this->getLastMondayOfMay($year)->format('m-d'),
            // Labor Day (first Monday in September)
            $this->getFirstMondayOfSeptember($year)->format('m-d'),
            // Thanksgiving (fourth Thursday in November)
            $this->getFourthThursdayOfNovember($year)->format('m-d'),
        );
        
        return in_array($monthDay, $calculatedHolidays);
    }
    
    /**
     * Get base date for scheduling calculations
     * 
     * @param array $dealData Deal data
     * @param array $options Options
     * @return DateTime Base date
     */
    private function getBaseDate($dealData, $options)
    {
        $baseDateSource = $options['base_date_source'] ?? 'now';
        
        switch ($baseDateSource) {
            case 'deal_entered':
                return new DateTime($dealData['date_entered']);
            case 'deal_close':
                return new DateTime($dealData['deal_close_date'] ?? 'now');
            case 'stage_entered':
                return new DateTime($dealData['stage_entered_date_c'] ?? 'now');
            case 'custom':
                return new DateTime($options['custom_base_date'] ?? 'now');
            default:
                return new DateTime();
        }
    }
    
    /**
     * Get time zone for scheduling
     * 
     * @param array $dealData Deal data
     * @param array $options Options
     * @return string Time zone
     */
    private function getTimeZone($dealData, $options)
    {
        // Priority: options > user preference > deal location > default
        if (isset($options['time_zone'])) {
            return $options['time_zone'];
        }
        
        // Try to get user's time zone
        global $current_user;
        if (!empty($current_user->id)) {
            $userTimeZone = $current_user->getPreference('timezone');
            if ($userTimeZone) {
                return $userTimeZone;
            }
        }
        
        // Try to determine from deal location
        if (!empty($dealData['account_country'])) {
            $timeZone = $this->getTimeZoneFromCountry($dealData['account_country']);
            if ($timeZone) {
                return $timeZone;
            }
        }
        
        return $this->defaultTimeZone;
    }
    
    /**
     * Get time zone from country
     * 
     * @param string $country Country name or code
     * @return string|null Time zone
     */
    private function getTimeZoneFromCountry($country)
    {
        $countryTimeZones = array(
            'US' => 'America/New_York',
            'USA' => 'America/New_York',
            'United States' => 'America/New_York',
            'CA' => 'America/Toronto',
            'Canada' => 'America/Toronto',
            'GB' => 'Europe/London',
            'UK' => 'Europe/London',
            'United Kingdom' => 'Europe/London',
            'DE' => 'Europe/Berlin',
            'Germany' => 'Europe/Berlin',
            'FR' => 'Europe/Paris',
            'France' => 'Europe/Paris',
            'AU' => 'Australia/Sydney',
            'Australia' => 'Australia/Sydney',
        );
        
        return $countryTimeZones[$country] ?? null;
    }
    
    /**
     * Load holidays from configuration
     */
    private function loadHolidays()
    {
        // Default US holidays
        $this->holidays = array(
            'exact' => array(),
            'recurring' => array(
                '01-01', // New Year's Day
                '07-04', // Independence Day
                '12-25', // Christmas Day
            )
        );
        
        // Load custom holidays from configuration if available
        // This could be extended to load from database or config files
    }
    
    /**
     * Get last Monday of May (Memorial Day)
     * 
     * @param int $year Year
     * @return DateTime Date
     */
    private function getLastMondayOfMay($year)
    {
        $date = new DateTime("last monday of may $year");
        return $date;
    }
    
    /**
     * Get first Monday of September (Labor Day)
     * 
     * @param int $year Year
     * @return DateTime Date
     */
    private function getFirstMondayOfSeptember($year)
    {
        $date = new DateTime("first monday of september $year");
        return $date;
    }
    
    /**
     * Get fourth Thursday of November (Thanksgiving)
     * 
     * @param int $year Year
     * @return DateTime Date
     */
    private function getFourthThursdayOfNovember($year)
    {
        $date = new DateTime("fourth thursday of november $year");
        return $date;
    }
}