<?php

namespace App\Auth;

use Illuminate\Auth\SessionGuard;
use Symfony\Component\HttpFoundation\Request;

class IsolatedSessionGuard extends SessionGuard
{
    public function setRequest(Request $request): static
    {
        if ($this->request !== $request) {
            $this->user = null;
            $this->loggedOut = false;
            $this->viaRemember = false;
            $this->recallAttempted = false;
        }
        parent::setRequest($request);

        return $this;
    }
}
