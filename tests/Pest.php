<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit', 'Browser');

pest()->browser()->timeout(10_000);

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Selects a Radix UI Select option by its visible text.
 *
 * A native Playwright pointer click on a Radix `[role="option"]` item hangs
 * until timeout in this sandboxed browser environment (confirmed via direct
 * reproduction: the element is present, visible, and a JS-dispatched click
 * on it works instantly and correctly updates app state — only Playwright's
 * coordinate-based click hangs, most likely an actionability/hit-testing
 * quirk with Radix's Portal + floating-ui positioned content here). Dispatch
 * the click via JS instead of the coordinate-based Playwright click to work
 * around it, since the trigger click that opens the popover works fine.
 */
function selectRadixOption(mixed $page, string $triggerSelector, string $optionText): mixed
{
    $page->click($triggerSelector);

    $escaped = addslashes($optionText);
    $page->script("(() => {
        const opts = Array.from(document.querySelectorAll('[role=\"option\"]'));
        const match = opts.find((o) => o.textContent.trim() === '{$escaped}');
        if (match) { match.click(); }
    })()");

    return $page;
}
