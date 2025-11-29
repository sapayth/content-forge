<?php

namespace BitApps\WPTelemetry\Telemetry\Report;

use BitApps\WPTelemetry\Telemetry\Telemetry;
use BitApps\WPTelemetry\Telemetry\TelemetryConfig;

class Report
{
    private $addPluginData = false;

    public function init()
    {
        $this->initCommonHooks();

        add_action(TelemetryConfig::getPrefix() . 'activate', [$this, 'activatePlugin']);

        add_action(TelemetryConfig::getPrefix() . 'deactivate', [$this, 'deactivatePlugin']);
    }

    public function addPluginData()
    {
        $this->addPluginData = true;

        return $this;
    }

    public function initCommonHooks()
    {
        if (!$this->isTrackingNoticeDismissed()) {
            add_action('admin_notices', [$this, 'adminNotice']);
        }

        add_action('admin_init', [$this, 'handleTrackingOptInOptOut']);

        add_filter('cron_schedules', [$this, 'addWeeklySchedule']);

        add_action(TelemetryConfig::getPrefix() . 'send_tracking_event', [$this, 'sendTrackingReport']);
    }

    public function adminNotice()
    {
        if ($this->isTrackingNoticeDismissed() || $this->isTrackingAllowed() || !current_user_can('manage_options')) {
            return;
        }

        Telemetry::view('reportOptIn', [
            'termsUrl'    => TelemetryConfig::getTermsUrl(),
            'policyUrl'   => TelemetryConfig::getPolicyUrl(),
            'optInUrl'    => wp_nonce_url(add_query_arg(TelemetryConfig::getPrefix() . 'tracking_opt_in', 'true'), '_wpnonce'),
            'optOutUrl'   => wp_nonce_url(add_query_arg(TelemetryConfig::getPrefix() . 'tracking_opt_out', 'true'), '_wpnonce'),
            'prefix'      => TelemetryConfig::getPrefix(),
            'title'       => TelemetryConfig::getTitle(),
            'description' => $this->getDescription(),
            'dataWeCollect' => $this->dataWeCollect(),
        ]);
    }

    public function getDescription()
    {
        return sprintf(
            // Translators: The user name and the plugin name.
            esc_html__(
                'Hi %1$s! Help us make %2$s even better by sharing anonymous usage data. This helps us prioritize features, fix bugs faster, and improve your experience. Your privacy matters—see exactly what we collect below.',
                '%3$s'
            ),
            wp_get_current_user()->display_name,
            TelemetryConfig::getTitle(),
            TelemetryConfig::getSlug()
        );
    }

    public function handleTrackingOptInOptOut()
    {
        if (
            !isset($_GET['_wpnonce'])
            || !wp_verify_nonce(sanitize_key($_GET['_wpnonce']), '_wpnonce')
            || !current_user_can('manage_options')
        ) {
            return;
        }

        $optInKey = TelemetryConfig::getPrefix() . 'tracking_opt_in';

        if (isset($_GET[$optInKey]) && $_GET[$optInKey] === 'true') {
            $this->trackingOptIn();
            wp_safe_redirect(remove_query_arg($optInKey));
            exit;
        }

        $optOutKey = TelemetryConfig::getPrefix() . 'tracking_opt_out';

        if (isset($_GET[$optOutKey]) && $_GET[$optOutKey] === 'true') {
            $this->trackingOptOut();
            wp_safe_redirect(remove_query_arg($optOutKey));
            exit;
        }
    }

    public function trackingOptIn()
    {
        update_option(TelemetryConfig::getPrefix() . 'allow_tracking', true);
        update_option(TelemetryConfig::getPrefix() . 'tracking_notice_dismissed', true);

        // Clear last sent timestamp to allow immediate send on opt-in
        delete_option(TelemetryConfig::getPrefix() . 'tracking_last_sended_at');

        $this->clearScheduleEvent();
        $this->scheduleEvent();
        $this->sendTrackingReport();

        do_action(TelemetryConfig::getPrefix() . 'tracking_opt_in');
    }

    public function trackingOptOut()
    {
        update_option(TelemetryConfig::getPrefix() . 'allow_tracking', false);
        update_option(TelemetryConfig::getPrefix() . 'tracking_notice_dismissed', true);

        $this->trackingSkippedRequest();
        $this->clearScheduleEvent();

        do_action(TelemetryConfig::getPrefix() . 'tracking_opt_out');
    }

    public function addWeeklySchedule($schedules)
    {
        $schedules['weekly'] = [
            'interval' => 604800, // 1 week in seconds
            'display'  => __('Once Weekly')
        ];

        return $schedules;
    }

    public function activatePlugin()
    {
        if (!$this->isTrackingAllowed()) {
            return;
        }

        $this->scheduleEvent();

        $this->sendTrackingReport();
    }

    public function deactivatePlugin()
    {
        $this->clearScheduleEvent();

        delete_option(TelemetryConfig::getPrefix() . 'tracking_notice_dismissed');
    }

    public function isTrackingAllowed()
    {
        return get_option(TelemetryConfig::getPrefix() . 'allow_tracking');
    }

    public function isTrackingNoticeDismissed()
    {
        return get_option(TelemetryConfig::getPrefix() . 'tracking_notice_dismissed');
    }

    public function sendTrackingReport()
    {
        $trackingAllowed = $this->isTrackingAllowed();
        $sendedWithinWeek = $this->isSendedWithinWeek();
        
        if (!$trackingAllowed || $sendedWithinWeek) {
            return;
        }

        // Update timestamp FIRST to prevent duplicate sends if function is called again
        $this->updateLastSendedAt();

        $trackingData = $this->getTrackingData();
        Telemetry::sendReport('plugin-track-create', $trackingData);
    }

    public function getTrackingData()
    {
        $reportInfo = new ReportInfo();

        $allPlugins = $reportInfo->getAllPlugins();

        $user_name = $reportInfo->getUserName();

        $data = [
            'url'              => esc_url(home_url()),
            'site'             => $reportInfo->getSiteName(),
            'first_name'       => $user_name['firstName'],
            'last_name'        => $user_name['lastName'],
            'server'           => $reportInfo->getServerInfo(),
            'wp'               => $reportInfo->getWpInfo(),
            'active_plugins'   => \count($allPlugins['activePlugins']),
            'inactive_plugins' => \count($allPlugins['inactivePlugins']),
            'plugin_slug'      => TelemetryConfig::getPrefix(),
            'plugin_version'   => TelemetryConfig::getversion(),
            'is_local'         => $reportInfo->isLocalServer(),
            'skipped'          => false
        ];

        if ($this->addPluginData) {
            $data['plugins'] = $reportInfo->getPluginInfo($allPlugins['activePlugins'], TelemetryConfig::getSlug());
        }

        if (get_option(TelemetryConfig::getPrefix() . 'tracking_skipped')) {
            delete_option(TelemetryConfig::getPrefix() . 'tracking_skipped');
            $data['previously_skipped'] = true;
        }

        $data['additional_data'] = apply_filters(TelemetryConfig::getPrefix() . 'telemetry_additional_data', []);

        return apply_filters(TelemetryConfig::getPrefix() . 'telemetry_data', $data);
    }

    protected function dataWeCollect()
    {
        $collectList = [
            'Server environment details (PHP version, MySQL version, server software, PHP configuration)',
            'WordPress information (version, locale, memory limit, debug mode, multisite status)',
            'Theme information (name, version, author, URI)',
            'Site name and URL',
            'Administrator first name and last name',
            'Number of active and inactive plugins',
            'Local server detection status',
        ];

        if ($this->addPluginData) {
            $collectList[] = 'Active plugins list (name and version)';
            $collectList[] = 'Inactive plugins list (name, version, and author)';
        }

        return $collectList;
    }

    private function trackingSkippedRequest()
    {
        $previouslySkipped = get_option(TelemetryConfig::getPrefix() . 'tracking_skipped');

        if (!$previouslySkipped) {
            update_option(TelemetryConfig::getPrefix() . 'tracking_skipped', true);
        }

        $data = [
            'url'                => esc_url(home_url()),
            'plugin_slug'        => TelemetryConfig::getPrefix(),
            'plugin_version'     => TelemetryConfig::getversion(),
            'skipped'            => true,
            'previously_skipped' => $previouslySkipped,
        ];

        Telemetry::sendReport('plugin-track-create', $data);
    }

    private function scheduleEvent()
    {
        $hook_name = TelemetryConfig::getPrefix() . 'send_tracking_event';

        if (!wp_next_scheduled($hook_name)) {
            wp_schedule_event(time(), 'weekly', $hook_name);
        }
    }

    private function clearScheduleEvent()
    {
        return wp_clear_scheduled_hook(TelemetryConfig::getPrefix() . 'send_tracking_event');
    }

    private function isSendedWithinWeek()
    {
        $lastSendedAt = $this->lastSendedAt();

        return $lastSendedAt && $lastSendedAt > strtotime('-1 week');
    }

    private function lastSendedAt()
    {
        return get_option(TelemetryConfig::getPrefix() . 'tracking_last_sended_at');
    }

    private function updateLastSendedAt()
    {
        return update_option(TelemetryConfig::getPrefix() . 'tracking_last_sended_at', time());
    }
}
