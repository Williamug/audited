<?php

namespace Williamug\Audited\Tests\Feature;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Williamug\Audited\Tests\Fixtures\TestUser;
use Williamug\Audited\Tests\TestCase;

class LogAuthEventsTest extends TestCase
{
    public function test_login_event_writes_a_log_entry(): void
    {
        $user = TestUser::create(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'password' => 'secret']);

        event(new Login('web', $user, false));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'login',
            'module' => 'Authentication',
            'user_name' => 'Jane Doe',
            'description' => "User 'Jane Doe' logged in.",
        ]);
    }

    public function test_logout_event_writes_a_log_entry(): void
    {
        $user = TestUser::create(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'password' => 'secret']);

        event(new Logout('web', $user));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'logout',
            'module' => 'Authentication',
            'description' => "User 'Jane Doe' logged out.",
        ]);
    }

    public function test_logout_event_with_null_user_does_not_write_a_log(): void
    {
        event(new Logout('web', null));

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_failed_login_event_writes_a_log_entry(): void
    {
        event(new Failed('web', null, ['email' => 'unknown@example.com', 'password' => 'wrong']));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'failed_login',
            'module' => 'Authentication',
            'description' => "Failed login attempt for 'unknown@example.com'.",
        ]);
    }

    public function test_failed_login_uses_configured_credential_field(): void
    {
        config(['audit.login_credential_field' => 'phone_number']);

        event(new Failed('web', null, ['phone_number' => '0777000111', 'password' => 'wrong']));

        $this->assertDatabaseHas('audit_logs', [
            'description' => "Failed login attempt for '0777000111'.",
        ]);
    }

    public function test_failed_login_falls_back_to_unknown_when_credential_missing(): void
    {
        event(new Failed('web', null, ['password' => 'wrong'])); // no email key

        $this->assertDatabaseHas('audit_logs', [
            'description' => "Failed login attempt for 'unknown'.",
        ]);
    }

    public function test_auth_module_label_is_configurable(): void
    {
        config(['audit.auth_module' => 'Security']);

        $user = TestUser::create(['name' => 'Jane', 'email' => 'jane@example.com', 'password' => 'secret']);

        event(new Login('web', $user, false));

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'Security',
        ]);
    }
}
