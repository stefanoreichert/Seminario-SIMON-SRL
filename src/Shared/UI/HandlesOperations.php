<?php

namespace Simon\Shared\UI;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Simon\Shared\Domain\BusinessRuleViolation;
use Simon\Shared\Domain\PermissionDenied;

trait HandlesOperations
{
    protected function perform(Closure $operation, string $message): RedirectResponse
    {
        try {
            $operation();
        } catch (PermissionDenied $exception) {
            abort(403, $exception->getMessage());
        } catch (BusinessRuleViolation $exception) {
            throw ValidationException::withMessages(['operation' => $exception->getMessage()]);
        }

        return back()->with('status', $message);
    }
}
