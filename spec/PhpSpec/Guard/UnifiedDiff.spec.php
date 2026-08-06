<?php

use PhpSpec\Guard\UnifiedDiff;

describe(UnifiedDiff::class, function () {

    it('numbers added lines as the file now stands', function () {
        $diff = <<<DIFF
        diff --git a/src/App/Basket.php b/src/App/Basket.php
        --- a/src/App/Basket.php
        +++ b/src/App/Basket.php
        @@ -10,0 +11,2 @@ class Basket
        +        if (\$this->coupon !== null) {
        +            return 0;
        DIFF;

        expect(UnifiedDiff::added($diff))->toBe(['src/App/Basket.php' => [11, 12]]);
    });

    it('counts context lines but not removed ones', function () {
        $diff = <<<DIFF
        --- a/src/App/Basket.php
        +++ b/src/App/Basket.php
        @@ -5,4 +5,4 @@
         public function total(): int
        -    return 0;
        +    return \$this->sum();
         }
        DIFF;

        // The context line is 5, the added line takes 6, and the removed line
        // is not in the file any more so it never had a number.
        expect(UnifiedDiff::added($diff))->toBe(['src/App/Basket.php' => [6]]);
    });

    it('has nothing to say about a hunk that only removes', function () {
        $diff = <<<DIFF
        --- a/src/App/Basket.php
        +++ b/src/App/Basket.php
        @@ -5,2 +4,0 @@
        -    \$dead = true;
        -    unset(\$dead);
        DIFF;

        expect(UnifiedDiff::added($diff))->toBe([]);
    });

    it('ignores a file the change deleted', function () {
        $diff = <<<DIFF
        --- a/src/App/Old.php
        +++ /dev/null
        @@ -1,2 +0,0 @@
        -<?php
        -class Old {}
        DIFF;

        expect(UnifiedDiff::added($diff))->toBe([]);
    });

    it('keeps the lines of each file apart', function () {
        $diff = <<<DIFF
        --- a/src/A.php
        +++ b/src/A.php
        @@ -1,0 +2,1 @@
        +one
        --- a/src/B.php
        +++ b/src/B.php
        @@ -7,0 +8,1 @@
        +two
        DIFF;

        expect(UnifiedDiff::added($diff))->toBe(['src/A.php' => [2], 'src/B.php' => [8]]);
    });

    it('has nothing to say about an empty diff', function () {
        expect(UnifiedDiff::added(''))->toBe([]);
    });

});
