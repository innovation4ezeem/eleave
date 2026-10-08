<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WarningLetter;
use App\Models\WarningLetterEvent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Builds only the tables it needs: the full migration history contains MySQL-only
 * statements (ALTER TABLE ... MODIFY COLUMN) that cannot run on the SQLite test database.
 */
class WarningLetterTest extends TestCase
{
    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role')->default('employee');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('employee_no')->nullable();
            $table->string('designation')->nullable();
            $table->date('joined_at')->nullable();
            // July 17 schema: a user is active when employee_status is not null (User::isActive()).
            $table->string('employee_status', 20)->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        (require database_path('migrations/2026_10_09_000001_create_warning_letters_table.php'))->up();
        (require database_path('migrations/2026_10_09_000002_add_audit_to_warning_letters.php'))->up();
    }

    private function makeUser(string $role, ?int $department, ?string $name = null, ?string $status = 'permanent'): User
    {
        $this->seq++;

        return User::factory()->create([
            'name' => $name ?? ucfirst($role).' Person '.$this->seq,
            'role' => $role,
            'department_id' => $department,
            'employee_status' => $status,
        ]);
    }

    private function makeLetter(User $employee, User $uploader, array $overrides = []): WarningLetter
    {
        $this->seq++;
        $path = 'warning-letters/'.str_repeat('a', 30).sprintf('%010d', $this->seq).'.pdf';
        Storage::disk('local')->put($path, 'PDF-CONTENT-'.$this->seq);

        return WarningLetter::create($overrides + [
            'user_id' => $employee->id,
            'issued_date' => '2026-09-01',
            'level' => 'Verbal',
            'notes' => null,
            'file_path' => $path,
            'original_filename' => 'letter-'.$this->seq.'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 20,
            'uploaded_by' => $uploader->id,
        ]);
    }

    private function validPayload(User $employee, array $overrides = []): array
    {
        return $overrides + [
            'user_id' => $employee->id,
            'issued_date' => '2026-10-01',
            'level' => '1st Written',
            'notes' => 'Internal HR note',
            'file' => UploadedFile::fake()->create('Final Notice.pdf', 200, 'application/pdf'),
        ];
    }

    public function test_migration_creates_the_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('warning_letters', [
            'id', 'user_id', 'issued_date', 'level', 'notes', 'file_path', 'original_filename',
            'mime_type', 'file_size', 'uploaded_by', 'created_at', 'updated_at', 'deleted_at',
        ]));
    }

    // ---- Admin: issue --------------------------------------------------------

    public function test_admin_can_upload_a_letter_to_the_private_disk(): void
    {
        $admin = $this->makeUser('admin', null);
        $employee = $this->makeUser('employee', 1);

        $this->actingAs($admin)
            ->post(route('admin.warning-letters.store'), $this->validPayload($employee))
            ->assertRedirect(route('admin.warning-letters.index'));

        $letter = WarningLetter::firstOrFail();
        $this->assertSame($employee->id, $letter->user_id);
        $this->assertSame($admin->id, $letter->uploaded_by);
        $this->assertSame('1st Written', $letter->level);
        $this->assertSame('Internal HR note', $letter->notes);
        $this->assertSame('Final Notice.pdf', $letter->original_filename);
        $this->assertSame('application/pdf', $letter->mime_type);
        $this->assertGreaterThan(0, $letter->file_size);

        // Random stored name, original name only in the database.
        $this->assertMatchesRegularExpression('#^warning-letters/[A-Za-z0-9]{40}\.pdf$#', $letter->file_path);
        $this->assertStringNotContainsString('Final', $letter->file_path);
        Storage::disk('local')->assertExists($letter->file_path);
    }

    public function test_level_accepts_any_text(): void
    {
        $admin = $this->makeUser('admin', null);
        $employee = $this->makeUser('employee', 1);

        $this->actingAs($admin)
            ->post(route('admin.warning-letters.store'), $this->validPayload($employee, ['level' => 'Suspension (3 days)']))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('warning_letters', ['level' => 'Suspension (3 days)']);
    }

    public function test_upload_rejects_bad_files_and_missing_fields(): void
    {
        $admin = $this->makeUser('admin', null);
        $employee = $this->makeUser('employee', 1);

        $this->actingAs($admin);

        $this->post(route('admin.warning-letters.store'), $this->validPayload($employee, ['file' => UploadedFile::fake()->create('virus.exe', 10)]))
            ->assertSessionHasErrors('file');
        $this->post(route('admin.warning-letters.store'), $this->validPayload($employee, ['file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain')]))
            ->assertSessionHasErrors('file');
        $this->post(route('admin.warning-letters.store'), $this->validPayload($employee, ['file' => UploadedFile::fake()->create('big.pdf', 5121, 'application/pdf')]))
            ->assertSessionHasErrors('file');

        $payload = $this->validPayload($employee);
        unset($payload['file']);
        $this->post(route('admin.warning-letters.store'), $payload)->assertSessionHasErrors('file');

        $this->post(route('admin.warning-letters.store'), $this->validPayload($employee, ['level' => '']))->assertSessionHasErrors('level');
        $this->post(route('admin.warning-letters.store'), $this->validPayload($employee, ['user_id' => 9999]))->assertSessionHasErrors('user_id');

        $this->assertSame(0, WarningLetter::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_upload_accepts_each_allowed_type_and_the_size_limit(): void
    {
        $admin = $this->makeUser('admin', null);
        $employee = $this->makeUser('employee', 1);

        $this->actingAs($admin);

        foreach ([['a.pdf', 'application/pdf'], ['b.jpg', 'image/jpeg'], ['c.jpeg', 'image/jpeg'], ['d.png', 'image/png']] as [$name, $mime]) {
            $this->post(route('admin.warning-letters.store'), $this->validPayload($employee, ['file' => UploadedFile::fake()->create($name, 100, $mime)]))
                ->assertSessionDoesntHaveErrors();
        }

        $this->post(route('admin.warning-letters.store'), $this->validPayload($employee, ['file' => UploadedFile::fake()->create('max.pdf', 5120, 'application/pdf')]))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(5, WarningLetter::count());
    }

    // ---- Admin: edit / delete ------------------------------------------------

    public function test_admin_can_edit_without_replacing_the_file(): void
    {
        $admin = $this->makeUser('admin', null);
        $employee = $this->makeUser('employee', 1);
        $letter = $this->makeLetter($employee, $admin);

        $this->actingAs($admin)
            ->put(route('admin.warning-letters.update', $letter), [
                'user_id' => $employee->id,
                'issued_date' => '2026-09-15',
                'level' => 'Final',
                'notes' => 'Updated note',
            ])
            ->assertRedirect(route('admin.warning-letters.index'));

        $letter->refresh();
        $this->assertSame('Final', $letter->level);
        $this->assertSame('Updated note', $letter->notes);
        $this->assertSame('2026-09-15', $letter->issued_date->format('Y-m-d'));
        Storage::disk('local')->assertExists($letter->file_path);
    }

    public function test_replacing_the_file_keeps_the_old_one_and_the_original_issuer(): void
    {
        $admin = $this->makeUser('admin', null);
        $otherAdmin = $this->makeUser('admin', null);
        $employee = $this->makeUser('employee', 1);
        $letter = $this->makeLetter($employee, $admin);
        $oldPath = $letter->file_path;
        $oldContent = Storage::disk('local')->get($oldPath);

        $this->actingAs($otherAdmin)
            ->put(route('admin.warning-letters.update', $letter), [
                'user_id' => $employee->id,
                'issued_date' => '2026-09-01',
                'level' => 'Verbal',
                'file' => UploadedFile::fake()->create('replacement.png', 50, 'image/png'),
            ])
            ->assertSessionDoesntHaveErrors();

        $letter->refresh();
        $this->assertNotSame($oldPath, $letter->file_path);
        $this->assertSame('replacement.png', $letter->original_filename);
        Storage::disk('local')->assertExists($letter->file_path);

        // uploaded_by stays the original issuer; only updated_by records who replaced the file.
        $this->assertSame($admin->id, $letter->uploaded_by);
        $this->assertSame($otherAdmin->id, $letter->updated_by);

        // The old file is renamed, not deleted.
        Storage::disk('local')->assertMissing($oldPath);
        $kept = Storage::disk('local')->allFiles('warning-letters/replaced');
        $this->assertCount(1, $kept);
        $this->assertMatchesRegularExpression('#^warning-letters/replaced/\d{8}-\d{6}-'.preg_quote(basename($oldPath), '#').'$#', $kept[0]);
        $this->assertSame($oldContent, Storage::disk('local')->get($kept[0]));

        // The event records the kept file's stored name.
        $event = WarningLetterEvent::where('action', 'file_replaced')->firstOrFail();
        $this->assertSame($otherAdmin->id, $event->user_id);
        $this->assertStringContainsString($kept[0], $event->details);
        $this->assertStringContainsString('replacement.png', $event->details);
    }

    public function test_delete_is_a_soft_delete_and_keeps_the_file(): void
    {
        $admin = $this->makeUser('admin', null);
        $employee = $this->makeUser('employee', 1);
        $letter = $this->makeLetter($employee, $admin);

        $this->actingAs($admin)
            ->delete(route('admin.warning-letters.destroy', $letter))
            ->assertRedirect(route('admin.warning-letters.index'));

        $this->assertSoftDeleted('warning_letters', ['id' => $letter->id]);
        Storage::disk('local')->assertExists($letter->file_path);

        $this->actingAs($admin)->get(route('warning-letters.download', $letter->id))->assertNotFound();
    }

    public function test_admin_list_shows_all_letters_and_filters_by_employee(): void
    {
        $admin = $this->makeUser('admin', null);
        $alice = $this->makeUser('employee', 1, 'Alice Alpha');
        $bob = $this->makeUser('employee', 2, 'Bob Bravo');
        $this->makeLetter($alice, $admin);
        $this->makeLetter($bob, $admin);

        $this->actingAs($admin)->get(route('admin.warning-letters.index'))
            ->assertOk()->assertSee('Alice Alpha')->assertSee('Bob Bravo');

        $response = $this->actingAs($admin)->get(route('admin.warning-letters.index', ['user_id' => $alice->id]));
        $response->assertOk();
        $this->assertCount(1, $response->viewData('letters'));
        $this->assertSame($alice->id, $response->viewData('letters')->first()->user_id);
    }

    public function test_admin_forms_render_with_level_suggestions(): void
    {
        $admin = $this->makeUser('admin', null);
        $employee = $this->makeUser('employee', 1);
        $letter = $this->makeLetter($employee, $admin, ['notes' => 'ADMIN-ONLY-NOTE']);

        $create = $this->actingAs($admin)->get(route('admin.warning-letters.create'))->assertOk();
        $create->assertSee('list="level-suggestions"', false);
        foreach (['Verbal', '1st Written', '2nd Written', 'Final', 'Other'] as $suggestion) {
            $create->assertSee('<option value="'.$suggestion.'">', false);
        }

        $this->actingAs($admin)->get(route('admin.warning-letters.edit', $letter))
            ->assertOk()->assertSee('ADMIN-ONLY-NOTE');
    }

    // ---- Non-admins cannot issue, edit or delete -----------------------------

    public static function nonAdminRoles(): array
    {
        return ['hod' => ['hod'], 'employee' => ['employee']];
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admins_get_403_on_every_management_route(string $role): void
    {
        $admin = $this->makeUser('admin', null);
        $actor = $this->makeUser($role, 1);
        $employee = $this->makeUser('employee', 1);
        $letter = $this->makeLetter($employee, $admin);

        $this->actingAs($actor);

        $this->get(route('admin.warning-letters.index'))->assertForbidden();
        $this->get(route('admin.warning-letters.create'))->assertForbidden();
        $this->post(route('admin.warning-letters.store'), $this->validPayload($employee))->assertForbidden();
        $this->get(route('admin.warning-letters.edit', $letter))->assertForbidden();
        $this->put(route('admin.warning-letters.update', $letter), [
            'user_id' => $employee->id, 'issued_date' => '2026-09-02', 'level' => 'Hacked',
        ])->assertForbidden();
        $this->delete(route('admin.warning-letters.destroy', $letter))->assertForbidden();

        $this->assertSame(1, WarningLetter::count());
        $this->assertSame('Verbal', $letter->fresh()->level);
        $this->assertNull($letter->fresh()->deleted_at);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_policy_checks_hold_even_without_the_route_middleware(): void
    {
        $admin = $this->makeUser('admin', null);
        $hod = $this->makeUser('hod', 1);
        $employee = $this->makeUser('employee', 1);
        $letter = $this->makeLetter($employee, $admin);

        foreach ([$hod, $employee] as $user) {
            $this->assertFalse(Gate::forUser($user)->allows('create', WarningLetter::class));
            $this->assertFalse(Gate::forUser($user)->allows('update', $letter));
            $this->assertFalse(Gate::forUser($user)->allows('delete', $letter));
            $this->assertFalse(Gate::forUser($user)->allows('manage', WarningLetter::class));
        }
        $this->assertTrue(Gate::forUser($admin)->allows('create', WarningLetter::class));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $letter));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $letter));
    }

    // ---- Read-only access rules ---------------------------------------------

    public function test_employee_sees_only_their_own_letters(): void
    {
        $admin = $this->makeUser('admin', null);
        $me = $this->makeUser('employee', 1, 'Emma Mine');
        $other = $this->makeUser('employee', 1, 'Oscar Other');
        $mine = $this->makeLetter($me, $admin, ['level' => 'MyLevelXYZ', 'notes' => 'SECRET-NOTE-123']);
        $this->makeLetter($other, $admin, ['level' => 'OtherLevelXYZ']);

        $response = $this->actingAs($me)->get(route('warning-letters.index'))->assertOk();

        $response->assertSee('My Warning Letters')->assertSee('MyLevelXYZ')
            ->assertDontSee('OtherLevelXYZ')->assertDontSee('SECRET-NOTE-123');
        $this->assertCount(1, $response->viewData('letters'));
        $this->assertSame($mine->id, $response->viewData('letters')->first()->id);
    }

    public function test_hod_sees_own_department_and_own_letters_only(): void
    {
        $admin = $this->makeUser('admin', null);
        $hod = $this->makeUser('hod', 1, 'Hana Head');
        $deptMate = $this->makeUser('employee', 1, 'Dept Mate');
        $outsider = $this->makeUser('employee', 2, 'Far Away');
        $noDept = $this->makeUser('employee', null, 'Nowhere Man');
        $this->makeLetter($deptMate, $admin, ['level' => 'MateLevel', 'notes' => 'SECRET-NOTE-123']);
        $this->makeLetter($hod, $admin, ['level' => 'HodOwnLevel']);
        $this->makeLetter($outsider, $admin, ['level' => 'OutsiderLevel']);
        $this->makeLetter($noDept, $admin, ['level' => 'NoDeptLevel']);

        $response = $this->actingAs($hod)->get(route('warning-letters.index'))->assertOk();

        $response->assertSee('Dept Mate')->assertSee('MateLevel')->assertSee('HodOwnLevel')
            ->assertDontSee('OutsiderLevel')->assertDontSee('Far Away')
            ->assertDontSee('NoDeptLevel')->assertDontSee('SECRET-NOTE-123');

        // Filtered in the query, not just hidden in the view.
        $this->assertCount(2, $response->viewData('letters')->items());
    }

    public function test_hod_without_a_department_only_matches_their_own_letters(): void
    {
        $admin = $this->makeUser('admin', null);
        $hod = $this->makeUser('hod', null, 'Lonely Head');
        $noDept = $this->makeUser('employee', null, 'Nowhere Man');
        $someone = $this->makeUser('employee', 1, 'Some One');
        $own = $this->makeLetter($hod, $admin, ['level' => 'LonelyOwn']);
        $orphan = $this->makeLetter($noDept, $admin, ['level' => 'NoDeptLevel']);
        $other = $this->makeLetter($someone, $admin, ['level' => 'SomeLevel']);

        $response = $this->actingAs($hod)->get(route('warning-letters.index'))->assertOk();
        $response->assertSee('LonelyOwn')->assertDontSee('NoDeptLevel')->assertDontSee('SomeLevel');
        $this->assertCount(1, $response->viewData('letters')->items());

        $this->assertTrue(Gate::forUser($hod)->allows('view', $own));
        $this->assertFalse(Gate::forUser($hod)->allows('view', $orphan));
        $this->assertFalse(Gate::forUser($hod)->allows('view', $other));
        $this->actingAs($hod)->get(route('warning-letters.download', $orphan))->assertForbidden();
    }

    public function test_letters_of_a_deactivated_employee_stay_visible_to_admin_and_department_hod(): void
    {
        $admin = $this->makeUser('admin', null);
        $hod = $this->makeUser('hod', 1);
        // July 17 semantics: employee_status null means deactivated (User::isActive() is false).
        $gone = $this->makeUser('employee', 1, 'Former Staff', null);
        $letter = $this->makeLetter($gone, $admin, ['level' => 'FormerLevel']);

        $this->assertFalse($gone->isActive());
        $this->actingAs($admin)->get(route('admin.warning-letters.index'))->assertOk()->assertSee('FormerLevel');
        $this->actingAs($hod)->get(route('warning-letters.index'))->assertOk()->assertSee('FormerLevel');
        $this->actingAs($hod)->get(route('warning-letters.download', $letter))->assertOk();
    }

    public function test_admin_visiting_the_read_only_list_is_sent_to_the_admin_list(): void
    {
        $admin = $this->makeUser('admin', null);

        $this->actingAs($admin)->get(route('warning-letters.index'))
            ->assertRedirect(route('admin.warning-letters.index'));
    }

    // ---- Download route ------------------------------------------------------

    public function test_download_streams_the_file_for_allowed_users(): void
    {
        $admin = $this->makeUser('admin', null);
        $hod = $this->makeUser('hod', 1);
        $employee = $this->makeUser('employee', 1);
        $letter = $this->makeLetter($employee, $admin, ['original_filename' => 'Final Notice.pdf']);
        $expected = Storage::disk('local')->get($letter->file_path);

        foreach ([$admin, $hod, $employee] as $user) {
            $response = $this->actingAs($user)->get(route('warning-letters.download', $letter));
            $response->assertOk()->assertDownload('Final Notice.pdf');
            $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
            $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
            $this->assertSame($expected, $response->streamedContent());
        }
    }

    public function test_download_is_denied_by_the_policy_not_just_hidden(): void
    {
        $admin = $this->makeUser('admin', null);
        $employee = $this->makeUser('employee', 1);
        $stranger = $this->makeUser('employee', 1);
        $otherDeptHod = $this->makeUser('hod', 2);
        $letter = $this->makeLetter($employee, $admin);

        $this->actingAs($stranger)->get(route('warning-letters.download', $letter))->assertForbidden();
        $this->actingAs($otherDeptHod)->get(route('warning-letters.download', $letter))->assertForbidden();
    }

    public function test_download_requires_login(): void
    {
        $admin = $this->makeUser('admin', null);
        $employee = $this->makeUser('employee', 1);
        $letter = $this->makeLetter($employee, $admin);

        $this->get(route('warning-letters.download', $letter))->assertRedirect(route('login'));
        $this->get(route('warning-letters.index'))->assertRedirect(route('login'));
    }

    public function test_download_returns_404_when_the_file_is_missing(): void
    {
        $admin = $this->makeUser('admin', null);
        $employee = $this->makeUser('employee', 1);
        $letter = $this->makeLetter($employee, $admin);
        Storage::disk('local')->delete($letter->file_path);

        $this->actingAs($admin)->get(route('warning-letters.download', $letter))->assertNotFound();
    }

    // ---- Notes are admin-only ------------------------------------------------

    public function test_notes_are_hidden_from_serialisation_and_never_rendered_for_non_admins(): void
    {
        $admin = $this->makeUser('admin', null);
        $employee = $this->makeUser('employee', 1);
        $letter = $this->makeLetter($employee, $admin, ['notes' => 'SECRET-NOTE-123']);

        $this->assertArrayNotHasKey('notes', $letter->toArray());
        $this->assertStringNotContainsString('SECRET-NOTE-123', $letter->toJson());

        $hod = $this->makeUser('hod', 1);
        foreach ([$employee, $hod] as $user) {
            $this->actingAs($user)->get(route('warning-letters.index'))->assertDontSee('SECRET-NOTE-123');
        }
    }

    // ---- Audit log -----------------------------------------------------------

    public function test_audit_columns_and_events_table_exist(): void
    {
        $this->assertTrue(Schema::hasColumn('warning_letters', 'updated_by'));
        $this->assertTrue(Schema::hasColumns('warning_letter_events', [
            'id', 'warning_letter_id', 'user_id', 'action', 'details', 'created_at',
        ]));
        $this->assertFalse(Schema::hasColumn('warning_letter_events', 'updated_at'));
    }

    public function test_every_admin_action_writes_an_event_and_never_logs_the_notes(): void
    {
        $admin = $this->makeUser('admin', null);
        $employee = $this->makeUser('employee', 1);

        $this->actingAs($admin)->post(route('admin.warning-letters.store'), $this->validPayload($employee, ['notes' => 'ISSUE-SECRET-1']));
        $letter = WarningLetter::firstOrFail();

        $this->actingAs($admin)->put(route('admin.warning-letters.update', $letter), [
            'user_id' => $employee->id, 'issued_date' => '2026-10-01', 'level' => 'Final', 'notes' => 'EDIT-SECRET-2',
        ]);
        $this->actingAs($admin)->put(route('admin.warning-letters.update', $letter), [
            'user_id' => $employee->id, 'issued_date' => '2026-10-01', 'level' => 'Final', 'notes' => 'EDIT-SECRET-2',
            'file' => UploadedFile::fake()->create('v2.pdf', 40, 'application/pdf'),
        ]);
        $this->actingAs($admin)->delete(route('admin.warning-letters.destroy', $letter));

        $events = WarningLetterEvent::orderBy('id')->get();
        $this->assertSame(['issued', 'edited', 'file_replaced', 'deleted'], $events->pluck('action')->all());
        $this->assertSame([$admin->id], $events->pluck('user_id')->unique()->values()->all());
        $this->assertSame([$letter->id], $events->pluck('warning_letter_id')->unique()->values()->all());
        $this->assertNotNull($events->first()->created_at);

        $this->assertStringContainsString('level: 1st Written', $events[0]->details);
        $this->assertStringContainsString('Final Notice.pdf', $events[0]->details);
        $this->assertSame('changed: level, notes', $events[1]->details);
        $this->assertStringContainsString('v2.pdf', $events[2]->details);

        foreach ($events as $event) {
            foreach (['ISSUE-SECRET-1', 'EDIT-SECRET-2', 'Internal HR note'] as $secret) {
                $this->assertStringNotContainsString($secret, (string) $event->details);
            }
            $this->assertLessThanOrEqual(255, strlen((string) $event->details));
        }
    }

    public function test_saving_without_any_change_writes_no_event_and_leaves_updated_by_empty(): void
    {
        $admin = $this->makeUser('admin', null);
        $employee = $this->makeUser('employee', 1);
        $letter = $this->makeLetter($employee, $admin);

        $this->actingAs($admin)->put(route('admin.warning-letters.update', $letter), [
            'user_id' => $employee->id,
            'issued_date' => $letter->issued_date->format('Y-m-d'),
            'level' => $letter->level,
        ])->assertRedirect(route('admin.warning-letters.index'));

        $this->assertSame(0, WarningLetterEvent::count());
        $this->assertNull($letter->fresh()->updated_by);
    }

    public function test_editing_fields_sets_updated_by_only(): void
    {
        $admin = $this->makeUser('admin', null);
        $otherAdmin = $this->makeUser('admin', null);
        $employee = $this->makeUser('employee', 1);
        $letter = $this->makeLetter($employee, $admin);

        $this->actingAs($otherAdmin)->put(route('admin.warning-letters.update', $letter), [
            'user_id' => $employee->id, 'issued_date' => '2026-09-20', 'level' => '2nd Written',
        ]);

        $letter->refresh();
        $this->assertSame($admin->id, $letter->uploaded_by);
        $this->assertSame($otherAdmin->id, $letter->updated_by);
        $this->assertSame('changed: issued_date, level', WarningLetterEvent::firstOrFail()->details);
    }

    public function test_changing_fields_and_file_together_writes_both_events(): void
    {
        $admin = $this->makeUser('admin', null);
        $employee = $this->makeUser('employee', 1);
        $letter = $this->makeLetter($employee, $admin);

        $this->actingAs($admin)->put(route('admin.warning-letters.update', $letter), [
            'user_id' => $employee->id, 'issued_date' => '2026-09-01', 'level' => 'Final',
            'file' => UploadedFile::fake()->create('both.pdf', 40, 'application/pdf'),
        ]);

        $this->assertSame(['edited', 'file_replaced'], WarningLetterEvent::orderBy('id')->pluck('action')->all());
    }

    public function test_every_download_is_logged_for_every_role_and_denied_ones_are_not(): void
    {
        $admin = $this->makeUser('admin', null);
        $hod = $this->makeUser('hod', 1);
        $employee = $this->makeUser('employee', 1);
        $stranger = $this->makeUser('employee', 2);
        $letter = $this->makeLetter($employee, $admin);

        foreach ([$admin, $hod, $employee] as $user) {
            $this->actingAs($user)->get(route('warning-letters.download', $letter))->assertOk();
        }
        $this->actingAs($stranger)->get(route('warning-letters.download', $letter))->assertForbidden();

        $events = WarningLetterEvent::where('action', 'downloaded')->orderBy('id')->get();
        $this->assertSame([$admin->id, $hod->id, $employee->id], $events->pluck('user_id')->all());
        $this->assertSame([$letter->id], $events->pluck('warning_letter_id')->unique()->values()->all());
        $this->assertSame(3, WarningLetterEvent::count());

        Storage::disk('local')->delete($letter->file_path);
        $this->actingAs($admin)->get(route('warning-letters.download', $letter))->assertNotFound();
        $this->assertSame(3, WarningLetterEvent::count());
    }

    public function test_admin_sees_the_history_on_the_edit_page(): void
    {
        $admin = $this->makeUser('admin', null, 'Alice Admin');
        $otherAdmin = $this->makeUser('admin', null, 'Bob Second');
        $employee = $this->makeUser('employee', 1);
        $letter = $this->makeLetter($employee, $admin);
        $this->actingAs($otherAdmin)->put(route('admin.warning-letters.update', $letter), [
            'user_id' => $employee->id, 'issued_date' => '2026-09-01', 'level' => 'Final',
        ]);
        $this->actingAs($employee)->get(route('warning-letters.download', $letter));

        $response = $this->actingAs($admin)->get(route('admin.warning-letters.edit', $letter))->assertOk();
        $response->assertSee('History')->assertSee('visible to admins only')
            ->assertSee('Edited')->assertSee('changed: level')->assertSee('Downloaded')
            ->assertSee('Bob Second')->assertSee('Issued by Alice Admin')->assertSee('last changed by Bob Second');
    }

    public function test_hod_and_employee_cannot_see_the_history(): void
    {
        $admin = $this->makeUser('admin', null);
        $hod = $this->makeUser('hod', 1);
        $employee = $this->makeUser('employee', 1);
        $letter = $this->makeLetter($employee, $admin, ['level' => 'PlainLevel']);
        $this->actingAs($admin)->put(route('admin.warning-letters.update', $letter), [
            'user_id' => $employee->id, 'issued_date' => '2026-09-01', 'level' => 'PlainLevel', 'notes' => 'N',
        ]);
        $this->actingAs($employee)->get(route('warning-letters.download', $letter));
        $this->assertGreaterThan(0, WarningLetterEvent::count());

        foreach ([$hod, $employee] as $user) {
            // The only page that shows history is the admin edit page, and it is closed to them.
            $this->actingAs($user)->get(route('admin.warning-letters.edit', $letter))->assertForbidden();

            $list = $this->actingAs($user)->get(route('warning-letters.index'))->assertOk();
            $list->assertSee('PlainLevel')->assertDontSee('History')->assertDontSee('Downloaded')
                ->assertDontSee('changed: ')->assertDontSee('Issued by');
            foreach ($list->viewData('letters') as $row) {
                $this->assertFalse($row->relationLoaded('events'));
            }
        }

        $this->assertTrue(Gate::forUser($this->makeUser('admin', null))->allows('viewHistory', $letter));
        $this->assertFalse(Gate::forUser($hod)->allows('viewHistory', $letter));
        $this->assertFalse(Gate::forUser($employee)->allows('viewHistory', $letter));
    }
}
