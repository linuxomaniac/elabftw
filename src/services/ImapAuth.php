<?php
/**
 * @author Linuxomaniac <linuxomaniac@saucisseroyale.cc>, Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2021 Linuxomaniac, Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
declare(strict_types=1);

namespace Elabftw\Services;

use Elabftw\Elabftw\AuthResponse;
use Elabftw\Exceptions\ImproperActionException;
use Elabftw\Exceptions\InvalidCredentialsException;
use Elabftw\Exceptions\ResourceNotFoundException;
use Elabftw\Interfaces\AuthInterface;
use Elabftw\Models\Teams;
use Elabftw\Models\Users;

/**
 * IMAP auth service
 */
class ImapAuth implements AuthInterface
{
    /** @var string $email */
    private $email = '';

    /** @var string $password */
    private $password = '';

    /** @var AuthResponse $AuthResponse */
    private $AuthResponse;

    /** @var array $configArr */
    private $configArr;

    public function __construct(array $configArr, string $email, string $password)
    {
        $this->configArr = $configArr;
        $this->email = Filter::sanitize($email);
        $this->password = $password;
        $this->AuthResponse = new AuthResponse('imap');
    }

    public function tryAuth(): AuthResponse
    {
        $handle = imap_open($this->configArr["auth_imap_mailbox"], $this->email, $this->password, OP_HALFOPEN|OP_READONLY);
        if (!$handle) {
            throw new InvalidCredentialsException();
        }

        $Users = new Users();
        try {
            $Users->populateFromEmail($this->email);
        } catch (ResourceNotFoundException $e) {
            // the user doesn't exist yet in the db
            // Try to extract first name and last name given the email address
            list($firstname, $lastname) = $this->extractNames();

            // GET DEFAULT TEAMS
            // we directly get the id from the stored config
            $teamId = (int) $this->configArr['auth_imap_team_default'];
            if ($teamId === 0) {
                throw new ImproperActionException('Could not find team ID to assign user!');
            }
            $Teams = new Teams($Users);
            $teams = $Teams->getTeamsFromIdOrNameOrOrgidArray(array($teamId))[0];

            // CREATE USER (and force validation of user)
            $Users = new Users($Users->create($this->email, $teams, $firstname, $lastname, '', null, true));
        }

        $this->AuthResponse->userid = (int) $Users->userData['userid'];
        $this->AuthResponse->mfaSecret = $Users->userData['mfa_secret'];
        $this->AuthResponse->setTeams();

        return $this->AuthResponse;
    }

    /* Extract first name and last name from email address (try to split on dot) */
    private function extractNames(): array
    {
        $localpart = explode('@', $this->email)[0];
        $names = explode('.', $localpart);

        $firstname = ucfirst($names[0]);

        if(!empty($names[1])) {
            $lastname = ucfirst($names[1]);
        } else {
            $lastname = 'Unknown';
        }

        return array($firstname, $lastname);
    }
}
