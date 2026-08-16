<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SessionAbsoluteTimeoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // ASVS 5.0.0 §7.3.2 leaves the duration to the application's own risk
        // analysis. 12h comes from ASVS 4.0.3 §3.3.2, which did mandate a number;
        // this is a reference build, not a real application, so it's just a
        // plausible example value — size it to your own risk profile.
        config(['session.absolute_lifetime' => 12 * 60]);
    }

    #[Test]
    public function user_stays_authenticated_before_the_absolute_lifetime_ends(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/')->assertOk();
        $this->assertAuthenticated();

        $this->travel(11)->hours();

        $this->get('/')->assertOk();
        $this->assertAuthenticated();
    }

    #[Test]
    public function user_is_logged_out_once_the_absolute_lifetime_elapses(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/')->assertOk();
        $this->assertAuthenticated();

        $this->travel(12)->hours();

        $this->get('/')->assertRedirect('/login');
        $this->assertGuest();
    }

    #[Test]
    public function user_is_logged_out_despite_continuous_activity(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/')->assertOk();
        $this->assertAuthenticated();

        for ($hour = 0; $hour < 11; $hour++) {
            $this->travel(1)->hours();
            $this->get('/')->assertOk();
        }

        $this->travel(1)->hours();

        $this->get('/')->assertRedirect('/login');
        $this->assertGuest();
    }

    #[Test]
    public function user_gets_401_for_json_requests_once_the_absolute_lifetime_elapses(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get('/')->assertOk();
        $this->assertAuthenticated();

        $this->travel(12)->hours();

        $this->getJson('/')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Session expired.']);
    }

}
