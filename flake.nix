{
  description = "RssBase, an RSS reader built on AT Protocol";

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

      php = import ./infra/packages/php.nix {
        inherit (pins) php86;
        icu = pkgs.icu79;
      };
      frankenphp = pkgs.callPackage ./infra/packages/frankenphp.nix { inherit php; };
      mago = pkgs.callPackage ./infra/packages/mago.nix { };
      symfony-lsp = pkgs.callPackage ./infra/packages/symfony-lsp.nix { };
      flarectl = pkgs.callPackage ./infra/packages/flarectl.nix { };
      docker-image-dev = pkgs.callPackage ./infra/packages/docker-image-dev.nix { inherit frankenphp; };

      shortcuts = pkgs.runCommand "shortcuts" { } ''
        mkdir -p $out/bin
        ln -s ${php.packages.castor}/bin/castor $out/bin/cas
        ln -s ${mago}/bin/mago $out/bin/mag
        ln -s ${frankenphp}/bin/frankenphp $out/bin/fra
      '';
    in
    {
      packages.${system} = {
        inherit
          php
          frankenphp
          mago
          symfony-lsp
          flarectl
          docker-image-dev
          ;
      };

      devShells.${system}.default = pkgs.mkShellNoCC {
        packages = [
          php
          php.packages.composer
          php.packages.castor
          frankenphp
          mago
          symfony-lsp
          flarectl
          pins.nodejs_26
          pkgs.jq
          pkgs.gnumake
          shortcuts
        ];

        # castor comes from this flake (ADR-0002): its own check for a newer release could only suggest a self-update.
        env.CASTOR_DISABLE_VERSION_CHECK = "1";
      };

      checks.${system} = {
        inherit
          php
          frankenphp
          mago
          symfony-lsp
          flarectl
          ;
      };
    };
}
