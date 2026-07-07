<?php

use PhpSpec\Coverage\HtmlReport;

describe(HtmlReport::class, function () {

    beforeEach(function () {
        $this->dir = sys_get_temp_dir() . '/phpspec_html_cov_' . uniqid();
    });

    afterEach(function () {
        if (is_dir($this->dir)) {
            array_map('unlink', glob($this->dir . '/*.html') ?: []);
            rmdir($this->dir);
        }
    });

    it('renders a branded index with the coverage table and meter', function () {
        $report = new HtmlReport();

        $report->render(['PhpSpec/Loader.php' => [1 => 1, 2 => -1]], $this->dir);

        $index = (string) file_get_contents($this->dir . '/index.html');
        expect($index)->toContain('<!DOCTYPE html>');
        expect($index)->toContain('class="wordmark"');
        expect($index)->toContain('class="meter"');
        expect($index)->toContain('class="coverage"');
        expect($index)->toContain('PhpSpec/Loader.php');
        expect($index)->toContain('1/2');
    });

    it('grades coverage bars by percentage', function () {
        $report = new HtmlReport();

        $report->render([
            'PhpSpec/Loader.php' => [1 => 1, 2 => 1],
            'PhpSpec/Runner.php' => [1 => 1, 2 => -1, 3 => -1, 4 => -1],
        ], $this->dir);

        $index = (string) file_get_contents($this->dir . '/index.html');
        expect($index)->toContain('class="hi"');
        expect($index)->toContain('class="lo"');
    });

    it('renders per-file source pages with hit and miss line classes', function () {
        $report = new HtmlReport();

        $report->render(['PhpSpec/Loader.php' => [17 => 1, 18 => -1]], $this->dir);

        $page = (string) file_get_contents($this->dir . '/PhpSpec_Loader.php.html');
        expect($page)->toContain('class="wordmark"');
        expect($page)->toContain('class="source"');
        expect($page)->toContain('<tr class="hit">');
        expect($page)->toContain('<tr class="miss">');
        expect($page)->toContain('Back to index');
    });
});
