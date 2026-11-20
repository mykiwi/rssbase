{
  description = "rssbase, an RSS reader built on AT Protocol";

  inputs.multiverse.url = "github:fzakaria/nixpkgs-multiverse";

  outputs =
    { multiverse, ... }:
    let
      system = "x86_64-linux";
      mv = multiverse.multiverse.${system};

      pkgs = mv.at "2026-11-19";
      pins = mv.solvePins {
        # Only the recipe: infra/packages/php.nix builds 8.6.0 from it until the index ships 8.6.0.
        php86 = "8.6.0RC4";
        nodejs_26 = "26.13.1";
      };

      php = import ./infra/packages/php.nix { inherit (pins) php86; };
      frankenphp = pkgs.callPackage ./infra/packages/frankenphp.nix { inherit php; };
      mago = pkgs.callPackage ./infra/packages/mago.nix { };
    in
    {
      packages.${system} = {
        inherit php frankenphp mago;
      };

      devShells.${system}.default = pkgs.mkShellNoCC {
        packages = [
          php
          php.packages.composer
          php.packages.castor
          frankenphp
          mago
          pins.nodejs_26
        ];
      };

      checks.${system} = {
        inherit php frankenphp mago;
      };
    };
}
