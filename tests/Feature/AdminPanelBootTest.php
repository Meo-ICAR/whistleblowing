<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminPanelBootTest extends TestCase
{
    /** @test */
    public function the_admin_login_page_boots_without_errors(): void
    {
        // Regressione: ->colors(['primary' => fn () => ...]) con la closure
        // su un singolo valore (anziché sull'intero array) manda in errore
        // fatale ColorManager su ogni pagina del pannello Filament.
        $response = $this->get('/admin/login');

        $response->assertOk();
    }

    /** @test */
    public function the_acme_demo_link_prefills_the_demo_credentials(): void
    {
        $response = $this->get('/admin/login?demo=acme');

        $response->assertOk();
        $response->assertSee('demo@acme-demo.test', false);
    }

    /** @test */
    public function the_plain_login_page_does_not_prefill_any_credentials(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk();
        $response->assertDontSee('demo@acme-demo.test');
        $response->assertDontSee('demo12345');
    }

    /** @test */
    public function the_login_page_shows_the_platform_logo_when_there_is_no_tenant(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk();
        $response->assertSee(asset('brand/logos/unico-whistle.jpg'), false);
    }
}
