<?php
namespace Tests\guest;

use App\Models\Exhibition;
use Database\Factories\GuestFactory;
use Illuminate\Support\Str;
use Tests\TestCase;
use App\Models\Guest;
use App\Models\Term;
use App\Models\User;
use Faker\Provider\DateTime;

/**
 * guests/$id/exit:post
 */
class ExitTest extends TestCase {
    public function testExit() {
        $user = User::factory()->permission('exhibition')->has(Exhibition::factory())->create();
        $guest = Guest::factory()->state(['exhibition_id'=>$user->id])->create();

        $this->actingAs($user)->post(
            "/guests/$guest->id/exit",
            ['exhibition_id' => $user->id]
        );
        $this->assertResponseOk();
    }

    public function testGuestNotFound() {
        $user = User::factory()->permission('exhibition')->has(Exhibition::factory())->create();
        Guest::factory()->create();
        $this->actingAs($user)->post(
            "/guests/GB-00000/exit",
            ['exhibition_id' => $user->id]
        );

        $this->assertResponseStatus(404);
        $this->assertJson($this->response->getContent());
        $code = json_decode($this->response->getContent())->error_code;
        $this->assertEquals('GUEST_NOT_FOUND', $code);
    }
    public function testAlreadyExited() {
        $user = User::factory()->permission('exhibition')->has(Exhibition::factory())->create();
        $executive_user = User::factory()->permission('executive')->create();
        $guest = Guest::factory()->create();

        $this->actingAs($executive_user)->post(
            "/guests/$guest->id/check-out"
        );

        $this->assertResponseOk();

        $this->actingAs($user)->post(
            "/guests/$guest->id/exit",
            ['exhibition_id' => $user->id]
        );

        $this->assertResponseStatus(400);
        $this->assertJson($this->response->getContent());
        $code = json_decode($this->response->getContent())->error_code;
        $this->assertEquals('GUEST_ALREADY_EXITED', $code);
    }

    public function testExhibitionNotFound() {
        $user = User::factory()->permission('exhibition')->create();
        $guest = Guest::factory()->create();

        $this->actingAs($user)->post(
            "/guests/$guest->id/exit",
            ['exhibition_id' => $user->id]
        );
        $this->assertResponseStatus(400);
        $this->assertJson($this->response->getContent());
        $code = json_decode($this->response->getContent())->error_code;
        $this->assertEquals('EXHIBITION_NOT_FOUND', $code);
    }

    public function testForbidden() {
        $users[] = User::factory()->permission('executive')->create();
        $users[] = User::factory()->create();

        foreach ($users as $user) {
            $this->actingAs($user)->post("/guests/GB_00000000/exit");
            $this->assertResponseStatus(403);
        }
    }

    public function testGuest() {
        $guest_id = Guest::factory()->create()->id;

        $this->post("/guests/$guest_id/exit");
        $this->assertResponseStatus(401);
    }
}
