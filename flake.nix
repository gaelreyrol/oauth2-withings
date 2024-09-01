{
  inputs = {
    nixpkgs.url = "github:NixOS/nixpkgs/nixpkgs-unstable";
    flake-utils.url = "github:numtide/flake-utils";

    treefmt-nix = {
      url = "github:numtide/treefmt-nix";
      inputs.nixpkgs.follows = "nixpkgs";
    };

    pre-commit-hooks = {
      url = "github:cachix/pre-commit-hooks.nix";
      inputs.nixpkgs.follows = "nixpkgs";
    };

    nix-php-shell = {
      url = "github:loophp/nix-shell";
      inputs.nixpkgs.follows = "nixpkgs";
    };
  };

  outputs = { self, nixpkgs, flake-utils, treefmt-nix, pre-commit-hooks, nix-php-shell }:
    flake-utils.lib.eachDefaultSystem (system:
      let
        pkgs = import nixpkgs {
          inherit system;
          overlays = [
            nix-php-shell.overlays.default
          ];
        };
        treefmtModule = treefmt-nix.lib.evalModule pkgs {
          projectRootFile = "flake.nix";
          programs = {
            nixpkgs-fmt.enable = true;
            php-cs-fixer.enable = true;
            yamlfmt.enable = true;
          };
          settings.formatter.php-cs-fixer = {
            options = [ "--config=.php-cs-fixer.dist.php" ];
          };
        };
        preCommitHooksConfig = {
          src = ./.;
          hooks = {
            statix.enable = true;
            markdownlint.enable = true;
            editorconfig-checker.enable = true;
            actionlint.enable = true;
          };
        };
        php = pkgs.api.buildPhpFromComposer { src = self; php = pkgs.php83; };
      in
      {
        formatter = treefmtModule.config.build.wrapper;

        checks = {
          pre-commit-check = pre-commit-hooks.lib.${system}.run preCommitHooksConfig;
          formatting = treefmtModule.config.build.check self;
        };

        devShells = {
          default = pkgs.mkShell {
            packages = [
              pkgs.cachix
              pkgs.nodePackages.markdownlint-cli
              pkgs.editorconfig-checker
              pkgs.actionlint

              php
              php.packages.composer
            ];
            inputsFrom = [
              treefmtModule.config.build.devShell
            ];
            inherit (self.checks."${system}".pre-commit-check) shellHook;
          };
        };

        apps = {
          composer =
            let
              binComposer = pkgs.writeShellApplication {
                name = "composer";
                runtimeInputs = [
                  php
                  php.packages.composer
                ];
                text = ''
                  ${pkgs.lib.getExe php.packages.composer} "$@"
                '';
              };
            in
            {
              type = "app";
              program = pkgs.lib.getExe binComposer;
            };
        };
      });
}
