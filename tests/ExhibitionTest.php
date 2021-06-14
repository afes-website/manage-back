<?php
namespace Tests;

use App\Models\Exhibition;
use App\Models\Guest;
use App\Models\Term;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Str;

/**
 * - guest/:get
 * - guest/$id:get
 */

class ExhibitionTest extends TestCase {
    public function testGetAll() {
        $count = 3;
        $user = User::factory()->permission('exhibition')->create();
        Exhibition::factory()->count($count)->create();

        $this->actingAs($user)->get('/exhibitions');
        $this->assertResponseOk();
        $this->assertJson($this->response->getContent());
        $res = json_decode($this->response->getContent());
        $this->assertCount($count, get_object_vars($res->exhibition));
    }

    public function testAllCounts() {
        $guest_count = 10;
        $term_count = 3;
        $exh_count = 2;
        $term = Term::factory()->count(3)->create();
        $user = User::factory()->permission('exhibition')->create();
        Exhibition::factory()
            ->has(
                Guest::factory()
                    ->count($guest_count * $term_count)
                    ->state(new Sequence(
                        ['term_id' => $term[0]->id],
                        ['term_id' => $term[1]->id],
                        ['term_id' => $term[2]->id]
                    ))
            )->for($user)->create();

        Exhibition::factory()
            ->has(
                Guest::factory()
                    ->count($guest_count * $term_count)
                    ->state(new Sequence(
                        ['term_id' => $term[0]->id],
                        ['term_id' => $term[1]->id],
                        ['term_id' => $term[2]->id]
                    ))
            )->count($exh_count - 1)->create();

        $this->actingAs($user)->get("/exhibitions");
        $this->assertResponseOk();
        $this->assertJson($this->response->getContent());

        $this->seeJsonContains([
            'all' => [
                $term[0]->id => $guest_count * $exh_count,
                $term[1]->id => $guest_count * $exh_count,
                $term[2]->id => $guest_count * $exh_count,
            ]
        ]);
    }

    public function testShowInfo() {
        $user = User::factory()->permission('exhibition')->create();
        $exhibition = Exhibition::factory()->create();

        $this->actingAs($user)->get("/exhibitions/$exhibition->id");
        $this->assertResponseOk();
        $this->assertJson($this->response->getContent());

        $this->seeJsonContains([
            'info' => [
                'room_id' => $exhibition->room_id,
                'name' => $exhibition->name,
                'thumbnail_image_id' => $exhibition->thumbnail_image_id,
            ],
            'capacity' => $exhibition->capacity,
            'count' => [],
        ]);
    }

    public function testShowCount() {
        $guest_count = 10;
        $term_count = 3;
        $term = Term::factory()->count(3)->create();
        $user = User::factory()->permission('exhibition')->create();
        $exhibition = Exhibition::factory()
            ->has(
                Guest::factory()
                    ->count($guest_count * $term_count)
                    ->state(new Sequence(
                        ['term_id' => $term[0]->id],
                        ['term_id' => $term[1]->id],
                        ['term_id' => $term[2]->id]
                    ))
            )->for($user)->create();

        $this->actingAs($user)->get("/exhibitions/$exhibition->id");
        $this->assertResponseOk();
        $this->assertJson($this->response->getContent());

        $this->seeJsonContains([
            'count' => [
                $term[0]->id => $guest_count,
                $term[1]->id => $guest_count,
                $term[2]->id => $guest_count,
            ]
        ]);
    }

    public function testCountExited() {
        $guest_count = 10;
        $user = User::factory()->permission('exhibition')->create();
        $term = Term::factory()->create();
        $exhibition = Exhibition::factory()
            ->has(
                Guest::factory()
                    ->for($term)
                    ->count($guest_count*2)
                    ->state(new Sequence([], ['exited_at' => Carbon::now()]))
            )
            ->for($user)
            ->create();

        $this->actingAs($user)->get("/exhibitions/$exhibition->id");
        $this->assertResponseOk();
        $this->assertJson($this->response->getContent());

        $this->assertEquals($guest_count, json_decode($this->response->getContent())->count->{$term->id});
    }

    public function testDontShowEmptyTerm() {
        $guest_count = 10;
        $user = User::factory()->permission('exhibition')->create();
        $exhibition = Exhibition::factory()
            ->for($user)
            ->has(Guest::factory()->count($guest_count*2))
            ->create();
        $term = Term::factory()->create();

        $this->actingAs($user)->get("/exhibitions/$user->id");
        $this->assertResponseOk();

        $this->seeJsonDoesntContains([
            $term->id => 0
        ]);
    }

    public function testNotFound() {
        $user = User::factory()->permission('exhibition')->create();
        $id = Str::random(8);
        Guest::factory()->create();
        $this->actingAs($user)->get("/exhibitions/$id");

        $this->assertResponseStatus(404);
    }
}
