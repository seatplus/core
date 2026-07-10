<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Browser smoke tests run against the fully-assembled core app (web + all
| packages), so Vite/manifest, @routes/@translations, providers, assets and
| auth all work natively — unlike the web package's Testbench harness.
|
| Seatplus package factory-name guessing is registered in Tests\TestCase::setUp().
|
*/

uses(Tests\TestCase::class)->in('Browser');

/*
|--------------------------------------------------------------------------
| Browser suite helpers
|--------------------------------------------------------------------------
|
| Shared provisioning for the browser tests (authored in seatplus/web, executed
| here). Lives in core's root Pest.php because that is the file Pest always loads
| for this suite — a Pest.php synced into tests/Browser/web is not auto-loaded.
|
*/

/**
 * Provision a logged-in user owning a single controlled character and return it.
 *
 * We create ONE CharacterInfo (its factory builds the affiliation/refresh-token/role)
 * linked as the main character rather than User::factory(), whose two-character graph
 * has intermittently-colliding CharacterAffiliation ids and renders pages blank. Queue
 * is faked so character-creation model events don't dispatch real ESI jobs.
 */
function actingAsCharacter(): \Seatplus\Eveapi\Models\Character\CharacterInfo
{
    \Illuminate\Support\Facades\Queue::fake();

    $character = \Seatplus\Eveapi\Models\Character\CharacterInfo::factory()->create();

    $user = new \Seatplus\Auth\Models\User;
    $user->main_character_id = $character->character_id;
    $user->save();

    \Seatplus\Auth\Models\CharacterUser::create([
        'user_id' => $user->getKey(),
        'character_id' => $character->character_id,
        'character_owner_hash' => sha1((string) $character->character_id),
    ]);

    \Seatplus\Web\Models\Onboarding::create(['user_id' => $user->getKey()]);

    test()->actingAs($user);

    return $character;
}

/**
 * Give a character an in-game corporation role (Director by default), which grants the
 * owning user corp-scoped access. CharacterInfo::factory() already creates an empty
 * CharacterRole, so update it in place.
 */
function giveCorporationRole(\Seatplus\Eveapi\Models\Character\CharacterInfo $character, string $role = 'Director'): void
{
    \Seatplus\Eveapi\Models\Character\CharacterRole::updateOrCreate(
        ['character_id' => $character->character_id],
        ['roles' => [$role]],
    );
}
