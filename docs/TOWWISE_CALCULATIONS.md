# TowWise Calculation Foundation

## Status

The repository now contains a pure calculation foundation. It is not a complete
TowWise user interface, vehicle database, legal assessment, or certification
service.

`App\TowWise\TowingCombinationCalculator` compares user-supplied masses with
user-supplied or separately sourced manufacturer limits. It deliberately has no
jurisdiction-specific legal rules.

## Current calculations

All values are kilograms.

```text
vehicle mass including towball = loaded vehicle mass + actual towball mass
combination mass = vehicle mass including towball + trailer GTM
GVM margin = vehicle GVM - vehicle mass including towball
GCM margin = vehicle GCM - combination mass
towing margin = maximum braked towing capacity - trailer ATM
towball margin = maximum towball limit - actual towball mass
```

Results are classified as:

- `within_known_limits`
- `near_known_limit` (a non-negative margin is at most 10% of its limit)
- `exceeds_known_limit`

## Input contract

Required positive values:

- vehicle GVM;
- vehicle GCM;
- maximum braked towing capacity;
- loaded vehicle mass;
- trailer ATM.

Other inputs are non-negative. Trailer GTM cannot exceed ATM. The application
layer must record the source, source date, exact vehicle/trailer variant, and
whether each value was entered, measured, imported, or independently verified.

## Important limitations

The current calculation optionally assesses entered towbar, coupling, front
axle, rear axle and trailer axle-group limits. It does not independently assess:

- tyre, wheel or chain limits;
- brake and breakaway requirements;
- dimensional restrictions;
- licence requirements;
- state or territory rules;
- manufacturer conditions beyond the supplied numeric limits;
- the effect of unrecorded accessories, occupants, cargo, fluids, or
  modifications.

The user interface must always explain that the result is an estimate based on
entered values, values must be confirmed, and actual loaded masses should be
measured where practical.

## Review position

TowWise is not marketed as authoritative, certified engineering or legal
advice. Independent specialist review is optional risk reduction rather than a
launch requirement. Transparent formulas, source attribution, automated tests
and careful non-authoritative wording remain mandatory.

## Public assessment experience

The feature-gated checker includes a local-browser load planner, actual mass
inputs, eleven possible component checks, a completeness indicator, visual
headroom, local scenario persistence, printable results, educational guidance
and contextual sponsored-placement support. The core checker remains free and
requires no account.
