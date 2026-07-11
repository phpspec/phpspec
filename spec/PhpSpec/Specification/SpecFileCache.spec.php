<?php

use PhpSpec\Specification\Context;
use PhpSpec\Specification\Rebindable;
use PhpSpec\Specification\SpecFileCache;
use PhpSpec\Specification\Subject;

describe(SpecFileCache::class, function () {

    // Each example uses a fresh, unique path, so entries never collide and no
    // global cache reset is needed (or offered — see SpecFileCache).
    beforeEach(function () {
        $this->file = sys_get_temp_dir() . '/phpspec_cache_' . uniqid() . '.spec.php';
        register_shutdown_function(fn() => @unlink($this->file));
    });

    it('parses a spec file into its top-level blocks', function () {
        file_put_contents($this->file, "<?php\ndescribe('Cached', function () { it('a', function () {}); });\n");

        $templates = SpecFileCache::templates($this->file);

        expect($templates)->toHaveCount(1);
        expect($templates[0])->toBeAnInstanceOf(Context::class);
        expect($templates[0])->toBeAnInstanceOf(Rebindable::class);
    });

    it('loads the file only once while its content is unchanged', function () {
        file_put_contents($this->file, "<?php\n\$GLOBALS['phpspec_cache_loads'] = (\$GLOBALS['phpspec_cache_loads'] ?? 0) + 1;\ndescribe('Cached', function () { it('a', function () {}); });\n");
        $GLOBALS['phpspec_cache_loads'] = 0;

        SpecFileCache::templates($this->file);
        SpecFileCache::templates($this->file);
        SpecFileCache::templates($this->file);

        expect($GLOBALS['phpspec_cache_loads'])->toBe(1);
    });

    it('reloads the file when its content changes', function () {
        file_put_contents($this->file, "<?php\n\$GLOBALS['phpspec_cache_loads'] = (\$GLOBALS['phpspec_cache_loads'] ?? 0) + 1;\ndescribe('One', function () { it('a', function () {}); });\n");
        $GLOBALS['phpspec_cache_loads'] = 0;

        SpecFileCache::templates($this->file);

        // A longer body changes the size component of the signature.
        file_put_contents($this->file, "<?php\n\$GLOBALS['phpspec_cache_loads'] = (\$GLOBALS['phpspec_cache_loads'] ?? 0) + 1;\ndescribe('Two', function () { it('a', function () {}); it('b', function () {}); });\n");

        SpecFileCache::templates($this->file);

        expect($GLOBALS['phpspec_cache_loads'])->toBe(2);
    });

    it('resolves different path strings for one file to a single entry', function () {
        file_put_contents($this->file, "<?php\n\$GLOBALS['phpspec_cache_loads'] = (\$GLOBALS['phpspec_cache_loads'] ?? 0) + 1;\ndescribe('Cached', function () { it('a', function () {}); });\n");
        $GLOBALS['phpspec_cache_loads'] = 0;

        SpecFileCache::templates($this->file);
        // The same file reached through a redundant "/./" segment must not reparse.
        SpecFileCache::templates(str_replace('/phpspec_cache_', '/./phpspec_cache_', $this->file));

        expect($GLOBALS['phpspec_cache_loads'])->toBe(1);
    });

    it('keeps templates pristine so each withWorld copy is a fresh, independent block', function () {
        file_put_contents($this->file, "<?php\ndescribe('Cached', function () { it('a', function () {}); });\n");

        $template = SpecFileCache::templates($this->file)[0];

        $first = $template->withWorld(new Subject());
        $second = $template->withWorld(new Subject());

        expect($first)->not()->toBe($template);
        expect($second)->not()->toBe($first);
    });
});
