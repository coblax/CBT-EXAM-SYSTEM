<?php

declare(strict_types=1);

namespace CbtExamSystem\Tests\Unit;

require_once dirname(__DIR__, 3) . '/includes/class-cbt-user-password-secret.php';

use CbtExamSystem\Tests\TestCase;

final class UserPasswordSecretTest extends TestCase
{
    public function test_encrypt_for_storage_uses_envelope_and_round_trips(): void
    {
        $stored = \CBT_User_Password_Secret::encrypt_for_storage('RahasiaKartu123!');

        self::assertStringStartsWith('cbtenc:v1:', $stored);
        self::assertNotSame('RahasiaKartu123!', $stored);
        self::assertSame('RahasiaKartu123!', \CBT_User_Password_Secret::decrypt_from_storage($stored));
    }

    public function test_legacy_plaintext_remains_readable(): void
    {
        self::assertSame('LegacyPass01', \CBT_User_Password_Secret::decrypt_from_storage('LegacyPass01'));
    }

    public function test_corrupt_encrypted_value_fails_closed(): void
    {
        self::assertSame('', \CBT_User_Password_Secret::decrypt_from_storage('cbtenc:v1:not-valid-base64'));
    }

    public function test_store_user_plain_password_writes_encrypted_meta(): void
    {
        \CBT_User_Password_Secret::store_user_plain_password(91, 'MetaPass99');

        $stored = (string) \get_user_meta(91, 'cbt_plain_password', true);
        self::assertStringStartsWith('cbtenc:v1:', $stored);
        self::assertNotSame('MetaPass99', $stored);
        self::assertSame('MetaPass99', \CBT_User_Password_Secret::get_user_plain_password(91));
    }

    public function test_get_user_plain_password_lazily_migrates_legacy_plaintext(): void
    {
        \update_user_meta(92, 'cbt_plain_password', 'LegacyMetaPass');

        self::assertSame('LegacyMetaPass', \CBT_User_Password_Secret::get_user_plain_password(92));

        $stored = (string) \get_user_meta(92, 'cbt_plain_password', true);
        self::assertStringStartsWith('cbtenc:v1:', $stored);
        self::assertSame('LegacyMetaPass', \CBT_User_Password_Secret::decrypt_from_storage($stored));
    }
}
