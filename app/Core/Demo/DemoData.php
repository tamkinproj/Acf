<?php

namespace App\Core\Demo;

use App\Core\Access\RoleSeeder;
use App\Core\Devices\DeviceService;
use App\Core\Documents\DocumentService;
use App\Core\Platform\FoundationProvisioner;
use App\Models\Aytam;
use App\Models\Family;
use App\Models\Foundation;
use App\Models\Guardian;
use App\Models\Organization;
use App\Models\OrganizationContact;
use App\Models\Program;
use App\Models\ProgramOrganization;
use App\Models\ProgramUser;
use App\Models\RegistrationForm;
use App\Models\Role;
use App\Models\User;
use App\Modules\Aytam\AytamService;
use App\Modules\Aytam\Registration\FormBuilder;
use App\Modules\Aytam\Registration\RegistrationService;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Sample data for DEVELOPMENT and demonstrations only. Everything it creates is labelled "Demo" (foundation, e-mail domain
 * demo.test) so it can never be mistaken for real records, and it refuses to run twice.
 */
class DemoData
{
    public const SLUG = 'demo-foundation';

    public function __construct(
        private TenantContext $tenant,
        private FoundationProvisioner $provisioner,
        private RoleSeeder $roles,
        private AytamService $aytam,
        private FormBuilder $forms,
        private RegistrationService $registrations,
        private DocumentService $documents,
        private DeviceService $devices,
    ) {}

    /** @return array{foundation:Foundation,password:string,accounts:array<string,string>,device_tokens:list<string>} */
    public function seed(string $password): array
    {
        if ($this->tenant->asSystem(fn () => Foundation::query()->where('slug', self::SLUG)->exists())) {
            throw new \RuntimeException('Demo data already exists.');
        }
        $this->roles->ensurePlatform();

        $result = $this->provisioner->create(
            ['name' => 'Demo Foundation', 'short_name' => 'Demo', 'country' => 'Philippines', 'description' => 'SAMPLE DATA for demonstrations. Not a real foundation.', 'email' => 'info@demo.test', 'slug' => self::SLUG],
            ['name' => 'Demo Foundation Admin', 'email' => 'admin@demo.test', 'password' => $password],
        );
        $foundation = $result['foundation'];
        $admin = $result['admin'];

        $tokens = [];
        $this->tenant->runAs($foundation->getKey(), function () use ($foundation, $admin, $password, &$tokens) {
            $member = fn (string $name, string $email, string $roleKey) => User::create([
                'name' => $name, 'email' => $email, 'password' => $password, 'status' => 'active',
                'role_id' => Role::query()->where('key', $roleKey)->value('id'),
            ]);
            // Program people hold a basic foundation role; what they can do in Aytam comes from their program role.
            $mushrif = $member('Demo Mushrif', 'mushrif@demo.test', 'volunteer');
            $worker = $member('Demo Field Worker', 'worker@demo.test', 'volunteer');

            $aytam = $this->program('Aytam care', 'aytam', 'Orphan care: records, families, guardians and documents.', Program::ACTIVE);
            $this->program('Flood relief', 'relief', 'Relief packages after the monsoon floods.', Program::ACTIVE);
            $this->program('Scholarships', 'education', 'School fees and supplies.', Program::DRAFT);

            foreach ([[$mushrif, 'aytam_mushrif'], [$worker, 'aytam_field_worker']] as [$user, $role]) {
                ProgramUser::create(['program_id' => $aytam->getKey(), 'user_id' => $user->getKey(), 'role_id' => Role::query()->where('key', $role)->value('id')]);
            }

            $org = Organization::create(['name' => 'Demo Partner Charity', 'type' => 'donor', 'country' => 'United Arab Emirates', 'location' => 'Dubai', 'email' => 'partner@demo.test', 'phone' => '+971 4 000 0000', 'status' => 'active']);
            OrganizationContact::create(['organization_id' => $org->getKey(), 'name' => 'Demo Contact', 'title' => 'Programme officer', 'email' => 'contact@demo.test', 'is_primary' => true]);
            ProgramOrganization::create(['program_id' => $aytam->getKey(), 'organization_id' => $org->getKey(), 'relationship' => 'funder', 'status' => 'active']);

            $this->children($aytam, $mushrif, $worker);
            $this->registrationForm($aytam, $mushrif);

            // Ready-made device tokens: a person who cannot register devices themselves pastes one when they first sign in.
            foreach ([1, 2, 3] as $n) {
                [, $token] = $this->devices->register("Demo phone {$n}", 'field', $admin->getKey(), foundationId: $foundation->getKey());
                $tokens[] = $token;
            }
        });

        return ['foundation' => $foundation, 'password' => $password, 'accounts' => [
            'Foundation Admin' => 'admin@demo.test', 'Aytam Mushrif' => 'mushrif@demo.test', 'Aytam Field Worker' => 'worker@demo.test',
        ], 'device_tokens' => $tokens];
    }

    private function program(string $name, string $category, string $description, string $status): Program
    {
        $module = \App\Programs\ProgramModules::moduleForCategory($category);

        return Program::create([
            'name' => $name, 'slug' => \Illuminate\Support\Str::slug($name), 'category' => $category, 'module' => $module?->key(), 'description' => $description,
            'status' => $status, 'config' => $module?->defaultConfig() ?: null,
        ]);
    }

    private function children(Program $program, User $mushrif, User $worker): void
    {
        $guardian = Guardian::create(['program_id' => $program->getKey(), 'full_name' => 'Salma Hassan', 'relationship' => 'Aunt', 'phone' => '0917 000 1111', 'email' => 'salma@demo.test']);
        $family = Family::create(['program_id' => $program->getKey(), 'name' => 'Abdullah family', 'father_name' => 'Abdullah Hassan', 'father_status' => 'deceased', 'mother_name' => 'Maryam Hassan', 'mother_status' => 'living',
            'guardian_id' => $guardian->getKey(), 'city' => 'Cotabato City', 'province' => 'Maguindanao', 'country' => 'Philippines']);

        $people = [
            ['Ahmad', null, 'Abdullah', '2012-04-17', 'male', Aytam::ACTIVE, $family->getKey(), 'Elementary', '5'],
            ['Bilal', null, 'Abdullah', '2014-02-02', 'male', Aytam::ACTIVE, $family->getKey(), 'Elementary', '3'],
            ['Fatima', null, 'Yusuf', '2010-09-30', 'female', Aytam::ACTIVE, null, 'High school', '8'],
            ['Zainab', null, 'Karim', '2015-06-12', 'female', Aytam::APPROVED, null, 'Elementary', '2'],
            ['Omar', 'bin', 'Hassan', '2011-11-03', 'male', Aytam::PENDING_REVIEW, null, 'Elementary', '6'],
            ['Aisha', null, 'Santos', '2013-01-21', 'female', Aytam::NEEDS_CORRECTION, null, 'Elementary', '4'],
            ['Yusuf', null, 'Ibrahim', '2009-07-08', 'male', Aytam::DRAFT, null, 'High school', '9'],
            ['Hadi', null, 'Malik', '2016-12-25', 'male', Aytam::INACTIVE, null, 'Pre-school', null],
        ];
        $created = [];
        foreach ($people as [$first, $middle, $last, $dob, $gender, $status, $familyId, $level, $grade]) {
            $record = $this->aytam->create($program, [
                'first_name' => $first, 'middle_name' => $middle, 'last_name' => $last, 'date_of_birth' => $dob, 'gender' => $gender, 'nationality' => 'Filipino',
                'country' => 'Philippines', 'province' => 'Maguindanao', 'city' => 'Cotabato City', 'barangay' => 'Rosary Heights', 'family_id' => $familyId,
                'education_level' => $level, 'school' => 'Demo Elementary School', 'grade' => $grade, 'phone' => '0917 000 22'.random_int(10, 99),
            ], $mushrif, 'manual', null, $status === Aytam::NEEDS_CORRECTION || $status === Aytam::PENDING_REVIEW || $status === Aytam::INACTIVE ? Aytam::DRAFT : $status);
            if (in_array($status, [Aytam::PENDING_REVIEW, Aytam::NEEDS_CORRECTION, Aytam::INACTIVE], true)) {
                $record->forceFill(['status' => $status, 'status_note' => $status === Aytam::NEEDS_CORRECTION ? 'Birth certificate is unreadable' : null])->save();
            }
            $created[] = $record;
        }
        \App\Models\AytamAssignment::create(['program_id' => $program->getKey(), 'aytam_id' => $created[0]->getKey(), 'user_id' => $worker->getKey(), 'assigned_by' => $mushrif->getKey()]);
        \App\Models\AytamAssignment::create(['program_id' => $program->getKey(), 'aytam_id' => $created[2]->getKey(), 'user_id' => $worker->getKey(), 'assigned_by' => $mushrif->getKey()]);

        $this->documents->upload($program, $this->pdf('birth-certificate.pdf'), ['type' => 'birth_certificate'], $mushrif, $created[0]);
        $this->documents->upload($program, $this->png('photo.png'), ['type' => 'photo'], $mushrif, $created[0]);
    }

    private function registrationForm(Program $program, User $mushrif): void
    {
        $form = RegistrationForm::create(['program_id' => $program->getKey(), 'title' => 'Aytam registration', 'description' => 'Please complete every section. Photos or scans of documents are required.', 'status' => RegistrationForm::DRAFT]);
        $this->forms->applyStandardTemplate($form);
        $version = $this->forms->publish($form, $mushrif);
        $form->refresh();

        $answers = fn (array $over) => $over + [
            'first_name' => 'Nur', 'last_name' => 'Rahman', 'date_of_birth' => '2013-03-09', 'gender' => 'female', 'nationality' => 'Filipino',
            'address' => ['country' => 'Philippines', 'province' => 'Maguindanao', 'city' => 'Cotabato City', 'barangay' => 'Rosary Heights'],
            'education_level' => 'Elementary', 'school' => 'Demo Elementary School', 'grade' => '4', 'father_name' => 'Rahman Ali', 'father_status' => 'deceased',
            'mother_name' => 'Hawa', 'mother_status' => 'living', 'guardian_name' => 'Hawa Rahman', 'guardian_relationship' => 'Mother', 'guardian_phone' => '0917 000 3333',
        ];
        $files = fn () => ['photo' => $this->png('child.png'), 'birth_certificate' => $this->pdf('birth.pdf')];

        $this->registrations->submit($form, $version, $answers([]), $files(), '203.0.113.5');
        $second = $this->registrations->submit($form, $version, $answers(['first_name' => 'Amir', 'last_name' => 'Said', 'gender' => 'male', 'guardian_name' => 'Said Omar']), $files(), '203.0.113.6');
        $this->registrations->sendBack($second['registration'], $mushrif, 'The photo is too dark. Please upload a clearer one.');
    }

    private function pdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<< /Type /Catalog >>endobj\ntrailer<< /Root 1 0 R >>\n%%EOF");
    }

    private function png(string $name): UploadedFile
    {
        return UploadedFile::fake()->image($name, 240, 240);
    }
}
