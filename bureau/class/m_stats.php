<?php

include('local.php');

/** Provides statistic exporter in (for now) prometheus format
 *  https://prometheus.io/docs/instrumenting/exposition_formats/
 */

interface metrics_dumper {
        function dump($collection);
}

class prometheus_export implements metrics_dumper {
        function __construct() { }
        function dump($collection) {
                foreach ($collection AS $k => $v) {
                        $tags = [];
                        foreach ($v AS $sk => $sv) {
                                if ($sk !== 'value') {
                                        $tags[] = sprintf("%s=%s", $sk, $sv);
                                }
                        }
                        printf("%s{%s} %s %s\n", $k, implode(',', $tags), $v['value'], time());
                }
        }
}

class m_stats {

        private $metrics = array();
        private $dumper;
        function __construct() {
                global $L_VERSION;
                $this->dumper = new prometheus_export();
                $this->metrics['alternc_version'] = array('value' => $L_VERSION);
        }

        function export_stats() {
                global $hooks;
                $other_metrics = $hooks->invoke('hook_stats');
                $formatted_metrics = array();
                foreach ($other_metrics AS $module => $stats) {
                        foreach ($stats AS $k => $v) {
                                $formatted_metrics[$module . '_' . $k] = $v;
                        }
                }
                $all = array_merge($this->metrics, $formatted_metrics);
                $this->dumper->dump($all);
        }
}
