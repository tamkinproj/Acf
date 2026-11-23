<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

/** A landing-page team card linked to a staff account: that person edits it; the change waits in the draft for the President. */
class SiteTeamSelfEditTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    private function linkCard(): array
    {
        $aytam = User::where('email', $this->accounts['staff:aytam'])->firstOrFail();
        $family = User::where('email', $this->accounts['user'])->firstOrFail();
        $this->actingAsRole('president_admin');
        $content = $this->getJson('/api/site')->assertOk()->json('data.content');
        $content['team'] = [
            ['visible' => true, 'photo' => null, 'name_en' => 'Aytam Staff', 'name_ar' => '', 'role_en' => 'Officer', 'role_ar' => '', 'bio_en' => '', 'bio_ar' => '', 'user_id' => $aytam->id],
            ['visible' => true, 'photo' => null, 'name_en' => 'Not linkable', 'name_ar' => '', 'role_en' => '', 'role_ar' => '', 'user_id' => $family->id],
        ];
        $saved = $this->putJson('/api/site', ['content' => $content])->assertOk()->json('data.content.team');
        $this->assertSame($aytam->id, $saved[0]['user_id']);
        $this->assertNull($saved[1]['user_id'], 'a family account cannot be linked to a team card');
        $this->postJson('/api/site/publish')->assertOk();

        return [$aytam, $family];
    }

    public function test_the_linked_person_edits_their_own_card_and_it_waits_for_the_president(): void
    {
        $this->world();
        $this->linkCard();
        $this->app['auth']->forgetGuards();
        $this->actingAsRole('staff:aytam');
        $mine = $this->getJson('/api/site/team/me')->assertOk()->json('data.cards');
        $this->assertCount(1, $mine);
        $this->assertTrue($mine[0]['published']);
        $after = $this->putJson('/api/site/team/me', ['index' => 0, 'name_en' => 'Amal Reyes', 'role_en' => 'Orphans officer', 'bio_en' => 'Visits the families every month.', 'bio_ar' => 'تزور الأسر كل شهر.'])->assertOk()->json('data.cards');
        $this->assertSame('Amal Reyes', $after[0]['name_en']);
        $this->assertFalse($after[0]['published'], 'waiting for the President to publish');
        $this->putJson('/api/site/team/me', ['index' => 1, 'name_en' => 'Not mine'])->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['action' => 'site.team_card_edited']);

        // the public page still shows the published card until the President publishes
        $this->assertSame('Aytam Staff', $this->getJson('/api/public/site')->json('data.content.team.0.name_en'));
        $this->app['auth']->forgetGuards();
        $this->actingAsRole('president_admin');
        $this->postJson('/api/site/publish')->assertOk();
        $public = $this->getJson('/api/public/site')->json('data.content.team.0');
        $this->assertSame('Amal Reyes', $public['name_en']);
        $this->assertSame('Visits the families every month.', $public['bio_en']);
        $this->assertArrayNotHasKey('user_id', $public, 'the linked account is not shown to visitors');
    }

    public function test_other_staff_and_family_accounts_cannot_edit_it(): void
    {
        $this->world();
        $this->linkCard();
        $this->app['auth']->forgetGuards();
        $this->actingAsRole('staff:finance');
        $this->assertSame([], $this->getJson('/api/site/team/me')->assertOk()->json('data.cards'));
        $this->putJson('/api/site/team/me', ['index' => 0, 'name_en' => 'Hijack'])->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->actingAsRole('user');
        $this->getJson('/api/site/team/me')->assertForbidden();
    }
}
