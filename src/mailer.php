<?php
/****************************************************************************************

Copyright 2014 Nathan Collins. All rights reserved.

Redistribution and use in source and binary forms, with or without modification, are
permitted provided that the following conditions are met:

   1. Redistributions of source code must retain the above copyright notice, this list of
      conditions and the following disclaimer.

   2. Redistributions in binary form must reproduce the above copyright notice, this list
      of conditions and the following disclaimer in the documentation and/or other materials
      provided with the distribution.

THIS SOFTWARE IS PROVIDED BY Nathan Collins ``AS IS'' AND ANY EXPRESS OR IMPLIED
WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND
FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL Nathan Collins OR
CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF
ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

The views and conclusions contained in the software and documentation are those of the
authors and should not be interpreted as representing official policies, either expressed
or implied, of Nathan Collins.

*****************************************************************************************/

/**
 * Prepare, validate, queue, and send emails
 */
class Mailer {
    // Queue
    private $aQueue;

    // Database mail storage
    private $db;
    private $aColumnMappings;

    // Errors
    private $aErrors;

    function __construct() {
        $this->aQueue = array();
        $this->aErrors = array();
    }

    public static function hasValidAddress($sAddress) {
    }

    public function prepareMail($aRecipients, $sSubject, $sMessage, $sFromAddr, $sReplyAddr) {
    }

    public function sendMail($aRecipients, $sSubject, $sMessage, $sFromAddr, $sReplyAddr) {
    }

    public function queueMail($aRecipients, $sSubject, $sMessage, $sFromAddr, $sReplyAddr) {
    }

    public function sendQueuedMails() {
    }

    public function discardQueuedMails() {
    }

    public function setDatabaseStore() {
    }

    public function storeQueuedMails() {
    }

    public function retrieveStoredEmails() {
    }

    /**
     * Checks if any errors have occurred while processing mail.
     * @return bool True if any errors happened, false otherwise
     */
    public function hasErrors() {
        return (count($this->aErrors) > 0);
    }

    /**
     * Get an array containing all errors that happened while processing mail
     * @return array An array of strings contianing the error messages; may be empty.
     */
    public function getErrrors() {
        return $this->aErrors;
    }

}

?>
