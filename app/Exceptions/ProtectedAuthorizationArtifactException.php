<?php

namespace App\Exceptions;

use RuntimeException;

class ProtectedAuthorizationArtifactException extends RuntimeException
{
    /**
     * @param string $roleName
     * @return ProtectedAuthorizationArtifactException
     */
    public static function roleDeletion(string $roleName): self
    {
        return new self("A(z) {$roleName} vedett rendszer-szerepkor, ezert nem torolheto.");
    }

    /**
     * @param string $roleName
     * @return ProtectedAuthorizationArtifactException
     */
    public static function roleIdentityUpdate(string $roleName): self
    {
        return new self("A(z) {$roleName} vedett rendszer-szerepkor neve vagy guardja nem modositheto.");
    }

    /**
     * @param string $permissionName
     * @return ProtectedAuthorizationArtifactException
     */
    public static function permissionDeletion(string $permissionName): self
    {
        return new self("A(z) {$permissionName} vedett rendszer-jogosultsag, ezert nem torolheto.");
    }

    /**
     * @param string $permissionName
     * @return ProtectedAuthorizationArtifactException
     */
    public static function permissionIdentityUpdate(string $permissionName): self
    {
        return new self("A(z) {$permissionName} vedett rendszer-jogosultsag neve vagy guardja nem modositheto.");
    }
}
