<?php

declare(strict_types=1);

namespace Assist\Tools\Documentation;

final class DocumentationChangeValidator
{
    /** @var list<string> */
    private const RELEASE_NOTES = ['docs/RELEASE_NOTES.md'];

    /**
     * @var array<string,array{label:string,patterns:list<string>,guides:list<string>}>
     */
    private const SCOPES = [
        'administrator' => [
            'label' => 'administrator interface',
            'patterns' => [
                '#^app/Views/admin/#i',
                '#^app/Views/layouts/admin\.php$#i',
                '#^public/assets/js/admin-platform\.js$#i',
                '#^app/Views/install/#i',
                '#^app/Controllers/Admin/#i',
                '#^app/Controllers/Install/#i',
                '#^routes/admin\.php$#i',
                '#^routes/install\.php$#i',
            ],
            'guides' => [
                'docs/ADMINISTRATOR_GUIDE.md',
                'docs/user-guide/administrator-guide/',
            ],
        ],
        'provider' => [
            'label' => 'provider or park interface',
            'patterns' => [
                '#^app/Views/(provider|park)/#i',
                '#^app/Controllers/(Provider|Park)/#i',
                '#^routes/(provider|park)\.php$#i',
            ],
            'guides' => [
                'docs/PROVIDER_GUIDE.md',
                'docs/USER_GUIDE.md',
                'docs/user-guide/provider-guide/',
            ],
        ],
        'public' => [
            'label' => 'public or customer interface',
            'patterns' => [
                '#^app/Views/(public|account|auth|brands|documentation|errors|regulatory|partials|towsmart|trailerwise)/#i',
                '#^app/Views/layouts/(?!admin\.php$)#i',
                '#^app/Controllers/(Site|Account|Auth)/#i',
                '#^routes/(web|account|auth)\.php$#i',
                '#^public/assets/css/#i',
                '#^public/assets/js/(?!admin-platform\.js$)#i',
                '#^public/(manifest\.webmanifest|service-worker\.js)$#i',
            ],
            'guides' => [
                'docs/USER_GUIDE.md',
                'docs/CUSTOMER_GUIDE.md',
                'docs/VANASSIST_USER_GUIDE.md',
                'docs/TOWSMART_USER_GUIDE.md',
                'docs/TRAILERWISE_USER_GUIDE.md',
                'docs/LOCALTORQUE_USER_GUIDE.md',
                'docs/user-guide/customer-guide/',
            ],
        ],
        'api' => [
            'label' => 'API contract',
            'patterns' => [
                '#^app/(Api|Controllers/Api)/#i',
                '#^routes/api\.php$#i',
            ],
            'guides' => [
                'docs/API.md',
                'docs/API_GUIDE.md',
                'docs/user-guide/api-guide/',
            ],
        ],
    ];

    /**
     * @param list<string> $changedFiles
     * @return list<string>
     */
    public function validate(array $changedFiles): array
    {
        $files = $this->normalise($changedFiles);
        $changed = array_fill_keys($files, true);
        $affectedScopes = $this->affectedScopes($files);
        if ($affectedScopes === []) {
            return [];
        }

        $errors = [];
        foreach ($affectedScopes as $scope) {
            $guides = self::SCOPES[$scope]['guides'];
            if (!$this->containsAny($changed, $guides)) {
                $errors[] = sprintf(
                    'Changed %s behaviour requires one matching guide update: %s.',
                    self::SCOPES[$scope]['label'],
                    implode(', ', $guides)
                );
            }
        }

        if (!$this->containsAny($changed, self::RELEASE_NOTES)) {
            $errors[] = 'Relevant interface/API/admin behaviour changed without updating docs/RELEASE_NOTES.md.';
        }

        return $errors;
    }

    /**
     * @param list<string> $files
     * @return list<string>
     */
    public function affectedScopes(array $files): array
    {
        $affected = [];
        foreach ($this->normalise($files) as $file) {
            foreach (self::SCOPES as $scope => $definition) {
                foreach ($definition['patterns'] as $pattern) {
                    if (preg_match($pattern, $file) === 1) {
                        $affected[$scope] = true;
                        break;
                    }
                }
            }
        }

        return array_keys($affected);
    }

    /** @param list<string> $files @return list<string> */
    private function normalise(array $files): array
    {
        $normalised = [];
        foreach ($files as $file) {
            $file = ltrim(str_replace('\\', '/', trim($file)), './');
            if ($file !== '') {
                $normalised[$file] = true;
            }
        }

        return array_keys($normalised);
    }

    /** @param array<string,bool> $changed @param list<string> $candidates */
    private function containsAny(array $changed, array $candidates): bool
    {
        foreach ($candidates as $candidate) {
            if (isset($changed[$candidate])) {
                return true;
            }
            if (str_ends_with($candidate, '/')) {
                foreach (array_keys($changed) as $file) {
                    if (str_starts_with($file, $candidate)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
