<?php
namespace Tests;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use \Carbon\Carbon;
use \Illuminate\Support\Str;

class AuthJwtTest extends TestCase {
    private static function getToken(TestCase $tc, $perms = []) {
        $password = Str::random(16);

        $data = [
            'password'=>Hash::make($password)
        ];
        foreach ($perms as $val) $data['perm_' . $val] = true;
        $user = User::factory()->create($data);
        $id = $user->id;
        $response = $tc->json(
            'POST',
            '/auth/login',
            ['id'=>$id, 'password'=>$password]
        );
        $response->assertResponseOk();
        $response->seeJsonStructure(['token']);

        $jwc_token = json_decode($response->response->getContent())->token;

        return [
            'user' => $user,
            'password' => $password,
            'auth_hdr' => ['Authorization' => "bearer {$jwc_token}"],
        ];
    }
    /**
     * /auth/login allow only post
     * @return void
     */
    public function testLoginGetNotAllowed() {
        $response = $this->get('/auth/login');
        $response->assertResponseStatus(405);
    }

    /**
     * /auth/user allow only get
     * @return void
     */
    public function testUserPostNotAllowed() {
        $response = $this->json('POST', '/auth/user');
        $response->assertResponseStatus(405);
    }

    /**
     * logging in with not existing user will be failed
     *
     * @return void
     */
    public function testUserNotFound() {
        $response = $this->json('POST', '/auth/login', [
            'id'=>Str::random(16),
            'password'=>Str::random(16)
        ]);
        $response->assertResponseStatus(401);
    }

    /**
     * logging in with wring password will be failed
     *
     * @return void
     */
    public function testPasswordWrong() {
        $user = $this->getToken($this);
        $response = $this->json('POST', '/auth/login', [
            'id'=>$user['user']->id,
            'password'=>Str::random(16)
        ]);
        $response->assertResponseStatus(401);
    }

    /**
     * login returns jwc token
     *
     * @return void
     */
    public function testLoginSuccessful() {
        // login and get token
        $user = $this->getToken($this);

        $response = $this->json('POST', '/auth/login', [
            'id'=>$user['user']->id,
            'password'=>$user['password']
        ]);
        $response->assertResponseOk();
        $response->seeJsonStructure(['token']);

        $jwc_token = json_decode($response->response->getContent())->token;

        $response = $this->get('/auth/user', ['Authorization'=>'bearer '.$jwc_token]);
        $response->assertResponseOk();
        $response->seeJson([
            'id'=>$user['user']->id,
            'name'=>$user['user']->name
        ]);
    }

    /**
     * user info permission bits
     * @return void
     */
    public function testPermissionObj() {
        $perms = [];
        foreach ([
            'admin',
            'reservation',
            'executive',
            'exhibition',
        ] as $name) {
            if (rand(0, 1) === 1) $perms[] = $name;
        }

        $user = $this->getToken($this, $perms);
        $response = $this->get('/auth/user', $user['auth_hdr']);
        $response->assertResponseOk();
        $response->seeJsonEquals([
            'id' => $user['user']->id,
            'name' => $user['user']->name,
            'permissions' => [
                'admin' => $user['user']->perm_admin,
                'reservation' => $user['user']->perm_reservation,
                'executive' => $user['user']->perm_executive,
                'exhibition' => $user['user']->perm_exhibition,
                'teacher' => $user['user']->perm_teacher,
            ],
        ]);
    }

    /**
     * user info denies access without token
     * @return void
     */
    public function testNoToken() {
        $response = $this->get('/auth/user');
        $response->assertResponseStatus(401);

        $response = $this->get('/auth/user', ['Authorization'=>'bearer invalid_token']);
        $response->assertResponseStatus(401);
    }

    /**
     * user info denies access with expired token
     * @return void
     */
    public function testExpiredToken() {
        // login and get token
        $user = $this->getToken($this);
        $cbi = CarbonImmutable::now();
        CarbonImmutable::setTestNow((new \DateTimeImmutable())->modify(env('JWT_EXPIRE'))->modify('+1 seconds'));
        // now token must be expired

        $response = $this->get('/auth/user', $user['auth_hdr']);
        $response->assertResponseStatus(401);
        CarbonImmutable::setTestNow($cbi);
    }

    /**
     * change password without login must be failed
     * @return void
     */
    public function testChangePasswordAnonymously() {
        $new_password = Str::random(16);
        $response = $this->json(
            'POST',
            '/auth/change_password',
            ['password'=>$new_password]
        );
        $response->assertResponseStatus(401);
    }

    /**
     * password less than 8 chars must be rejected
     * @return void
     */
    public function testWeakNewPassword() {
        // create user
        $old_password = Str::random(16); // initial does not matter
        $new_weak_password = Str::random(7); // < 8
        $new_strong_password = Str::random(8); // >= 8

        $user = User::factory()->create([
            'password'=>Hash::make($old_password)
        ]);
        $id = $user->id;

        // login first
        $response = $this->json(
            'POST',
            '/auth/login',
            ['id'=>$id, 'password'=>$old_password]
        );
        $response->assertResponseOk();
        $response->seeJsonStructure(['token']);

        $jwc_token = json_decode($response->response->getContent())->token;

        // weak password must be rejected
        $response = $this->actingAs($user)->json(
            'POST',
            '/auth/change_password',
            ['password'=>$new_weak_password]
        );
        $response->assertResponseStatus(400);

        // strong password must be accepted
        $response = $this->actingAs($user)->json(
            'POST',
            '/auth/change_password',
            ['password'=>$new_strong_password]
        );
        $response->assertResponseStatus(204);
    }

    /**
     * changing password
     * @return void
     */
    public function testChangePassword() {
        // create user
        $new_password = Str::random(16);
        $old_password = Str::random(16);
        $user = User::factory()->create([
            'password' => Hash::make($old_password)
        ]);

        $id = $user->id;

        $response = $this->actingAs($user)->json(
            'POST',
            '/auth/change_password',
            ['password' => $new_password],
        );
        $response->assertResponseStatus(204);

        // old password is no longer valid
        $response = $this->json(
            'POST',
            '/auth/login',
            ['id'=>$id, 'password'=>$old_password]
        );
        $response->assertResponseStatus(401);

        // use new password instead old one
        $response = $this->json(
            'POST',
            '/auth/login',
            ['id'=>$id, 'password'=>$new_password]
        );
        $response->assertResponseOk();
    }
}
