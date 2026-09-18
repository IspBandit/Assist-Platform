# VanAssist Ask Function Example Test Output

## Overview

This document shows example output from testing the VanAssist Ask function with multiple relevant questions. The tests demonstrate that the function correctly routes user queries to the appropriate intent types, categories, and URLs.

## TowSmart Examples

### Calculator Intent

**Query:** "Can I safely tow 3000kg with my Ranger?"

```
Kind: calculator
Category: null
Location: null
Heading: Check the exact towing combination
Explanation: TowSmart matched this to its calculation and safety guidance. Enter plate and manufacturer figures for the exact vehicle and trailer; the result is guidance, not certification.
URL: http://localhost/calculator
Source: TowSmart deterministic calculation-and-safety matrix
✅ PASSED
```

**Query:** "What is the GCM for my vehicle?"

```
Kind: calculator
Category: null
Location: null
Heading: Check the exact towing combination
Explanation: TowSmart matched this to its calculation and safety guidance. Enter plate and manufacturer figures for the exact vehicle and trailer; the result is guidance, not certification.
URL: http://localhost/calculator
Source: TowSmart deterministic calculation-and-safety matrix
✅ PASSED
```

### Guidance Intent

**Query:** "What does ATM mean?"

```
Kind: guidance
Category: null
Location: null
Heading: Read the towing definitions and calculation guide
Explanation: TowSmart matched this to reviewed explanatory content. Confirm ratings for the exact vehicle and trailer before relying on a calculation.
URL: http://localhost/tow-guide
Source: TowSmart deterministic education matrix
✅ PASSED
```

**Query:** "Define towball weight"

```
Kind: guidance
Category: null
Location: null
Heading: Read the towing definitions and calculation guide
Explanation: TowSmart matched this to reviewed explanatory content. Confirm ratings for the exact vehicle and trailer before relying on a calculation.
URL: http://localhost/tow-guide
Source: TowSmart deterministic education matrix
✅ PASSED
```

### Provider Categories

**Query:** "mobile weighing near Toowoomba"

```
Kind: providers
Category: public-weighing
Location: Toowoomba
Heading: Search the matching specialist category
Explanation: This request matched a curated towing service category. Confirm capabilities and current details with the business.
URL: http://localhost/providers?category=public-weighing&location=Toowoomba
Source: TowSmart deterministic provider-category matrix
✅ PASSED
```

**Query:** "towbar installer near Ipswich"

```
Kind: providers
Category: towbars-hitches
Location: Ipswich
Heading: Search the matching specialist category
Explanation: This request matched a curated towing service category. Confirm capabilities and current details with the business.
URL: http://localhost/providers?category=towbars-hitches&location=Ipswich
Source: TowSmart deterministic provider-category matrix
✅ PASSED
```

**Query:** "brake controller near Cairns"

```
Kind: providers
Category: brakes-controllers
Location: Cairns
Heading: Search the matching specialist category
Explanation: This request matched a curated towing service category. Confirm capabilities and current details with the business.
URL: http://localhost/providers?category=brakes-controllers&location=Cairns
Source: TowSmart deterministic provider-category matrix
✅ PASSED
```

**Query:** "suspension upgrade near Mackay"

```
Kind: providers
Category: suspension-payload
Location: Mackay
Heading: Search the matching specialist category
Explanation: This request matched a curated towing service category. Confirm capabilities and current details with the business.
URL: http://localhost/providers?category=suspension-payload&location=Mackay
Source: TowSmart deterministic provider-category matrix
✅ PASSED
```

**Query:** "towing training near Melbourne"

```
Kind: providers
Category: towing-training
Location: Melbourne
Heading: Search the matching specialist category
Explanation: This request matched a curated towing service category. Confirm capabilities and current details with the business.
URL: http://localhost/providers?category=towing-training&location=Melbourne
Source: TowSmart deterministic provider-category matrix
✅ PASSED
```

### "Near Me" Handling

**Query:** "mobile weighing near me" (WITH device location: "Brisbane City, QLD")

```
Kind: providers
Category: public-weighing
Location: Brisbane City, QLD
Heading: Search the matching specialist category
Explanation: This request matched a curated towing service category. Confirm capabilities and current details with the business.
URL: http://localhost/providers?category=public-weighing&location=Brisbane+City%2C+QLD
Source: TowSmart deterministic provider-category matrix
✅ PASSED
```

**Query:** "brake controller near me" (WITHOUT device location)

```
Kind: location
Category: null
Location: null
Heading: Add your current location to continue
Explanation: This request uses "near me", but no location has been supplied yet. Use the current-location control, or replace "near me" with a town or suburb.
URL: http://localhost/ask?q=brake+controller+near+me
Source: TowSmart device-location safeguard
✅ PASSED
```

### Clarify Intent

**Query:** "something unusual near Bundaberg"

```
Kind: clarify
Category: null
Location: Bundaberg
Heading: Choose a service category
Explanation: The request did not match a specific curated category, so no unrelated business has been substituted. Choose a category or refine the service you need.
URL: http://localhost/providers?location=Bundaberg
Source: TowSmart deterministic zero-result safeguard
✅ PASSED
```

## TrailerWise Examples

### Guidance Intent

**Query:** "trailer registration rules"

```
Kind: guidance
Category: null
Location: null
Heading: Open trailer ownership and compliance guidance
Explanation: TrailerWise matched this to its current rules and ownership pathway. Check the linked authority for your jurisdiction and trailer before acting.
URL: http://localhost/rules
Source: TrailerWise deterministic ownership-content matrix
✅ PASSED
```

**Query:** "maintenance schedule for trailers"

```
Kind: guidance
Category: null
Location: null
Heading: Open trailer ownership and compliance guidance
Explanation: TrailerWise matched this to its current rules and ownership pathway. Check the linked authority for your jurisdiction and trailer before acting.
URL: http://localhost/rules
Source: TrailerWise deterministic ownership-content matrix
✅ PASSED
```

### Provider Categories

**Query:** "mobile trailer service near Bendigo"

```
Kind: providers
Category: mobile-trailer-services
Location: Bendigo
Heading: Search the matching specialist category
Explanation: This request matched a curated trailer service category. Confirm capabilities and current details with the business.
URL: http://localhost/providers?category=mobile-trailer-services&location=Bendigo
Source: TrailerWise deterministic provider-category matrix
✅ PASSED
```

**Query:** "repair my trailer near Cairns"

```
Kind: providers
Category: trailer-repairs
Location: Cairns
Heading: Search the matching specialist category
Explanation: This request matched a curated trailer service category. Confirm capabilities and current details with the business.
URL: http://localhost/providers?category=trailer-repairs&location=Cairns
Source: TrailerWise deterministic provider-category matrix
✅ PASSED
```

**Query:** "roadworthy certifier near Hobart"

```
Kind: providers
Category: roadworthy-inspections
Location: Hobart
Heading: Search the matching specialist category
Explanation: This request matched a curated trailer service category. Confirm capabilities and current details with the business.
URL: http://localhost/providers?category=roadworthy-inspections&location=Hobart
Source: TrailerWise deterministic provider-category matrix
✅ PASSED
```

**Query:** "trailer bearings near Gladstone"

```
Kind: providers
Category: tyres-wheels-bearings
Location: Gladstone
Heading: Search the matching specialist category
Explanation: This request matched a curated trailer service category. Confirm capabilities and current details with the business.
URL: http://localhost/providers?category=tyres-wheels-bearings&location=Gladstone
Source: TrailerWise deterministic provider-category matrix
✅ PASSED
```

**Query:** "trailer brakes near Warwick"

```
Kind: providers
Category: brakes-axles-suspension
Location: Warwick
Heading: Search the matching specialist category
Explanation: This request matched a curated trailer service category. Confirm capabilities and current details with the business.
URL: http://localhost/providers?category=brakes-axles-suspension&location=Warwick
Source: TrailerWise deterministic provider-category matrix
✅ PASSED
```

**Query:** "trailer wiring near Caboolture"

```
Kind: providers
Category: auto-electrical
Location: Caboolture
Heading: Search the matching specialist category
Explanation: This request matched a curated trailer service category. Confirm capabilities and current details with the business.
URL: http://localhost/providers?category=auto-electrical&location=Caboolture
Source: TrailerWise deterministic provider-category matrix
✅ PASSED
```

**Query:** "welding near Gympie"

```
Kind: providers
Category: fabrication-engineering
Location: Gympie
Heading: Search the matching specialist category
Explanation: This request matched a curated trailer service category. Confirm capabilities and current details with the business.
URL: http://localhost/providers?category=fabrication-engineering&location=Gympie
Source: TrailerWise deterministic provider-category matrix
✅ PASSED
```

**Query:** "find trailer parts near Dubbo"

```
Kind: providers
Category: parts-accessories
Location: Dubbo
Heading: Search the matching specialist category
Explanation: This request matched a curated trailer service category. Confirm capabilities and current details with the business.
URL: http://localhost/providers?category=parts-accessories&location=Dubbo
Source: TrailerWise deterministic provider-category matrix
✅ PASSED
```

**Query:** "trailer manufacturer near Brisbane"

```
Kind: providers
Category: manufacturers-dealers
Location: Brisbane
Heading: Search the matching specialist category
Explanation: This request matched a curated trailer service category. Confirm capabilities and current details with the business.
URL: http://localhost/providers?category=manufacturers-dealers&location=Brisbane
Source: TrailerWise deterministic provider-category matrix
✅ PASSED
```

### "Near Me" Handling

**Query:** "trailer bearings near current location" (WITH device location: "Gladstone, QLD")

```
Kind: providers
Category: tyres-wheels-bearings
Location: Gladstone, QLD
Heading: Search the matching specialist category
Explanation: This request matched a curated trailer service category. Confirm capabilities and current details with the business.
URL: http://localhost/providers?category=tyres-wheels-bearings&location=Gladstone%2C+QLD
Source: TrailerWise deterministic provider-category matrix
✅ PASSED
```

**Query:** "trailer repair near me" (WITHOUT device location)

```
Kind: location
Category: null
Location: null
Heading: Add your current location to continue
Explanation: This request uses "near me", but no location has been supplied yet. Use the current-location control, or replace "near me" with a town or suburb.
URL: http://localhost/ask?q=trailer+repair+near+me
Source: TrailerWise device-location safeguard
✅ PASSED
```

### Clarify Intent

**Query:** "something unusual near Orange"

```
Kind: clarify
Category: null
Location: Orange
Heading: Choose a service category
Explanation: The request did not match a specific curated category, so no unrelated business has been substituted. Choose a category or refine the service you need.
URL: http://localhost/providers?location=Orange
Source: TrailerWise deterministic zero-result safeguard
✅ PASSED
```

## Edge Cases

### Case Insensitivity

**Query:** "MOBILE WEIGHING NEAR BRISBANE"

```
Kind: providers
Category: public-weighing
Location: Brisbane
✅ PASSED - Case insensitivity works correctly
```

### Whitespace Normalization

**Query:** "mobile    weighing    near    Brisbane"

```
Kind: providers
Category: public-weighing
Location: Brisbane
✅ PASSED - Multiple whitespace normalized correctly
```

### Location Precedence

**Query:** "trailer repair near me" (WITH device location: "Gladstone, QLD")

```
Location: Gladstone, QLD
✅ PASSED - Device location takes precedence
```

### URL Encoding

**Query:** "mobile weighing near Brisbane City, QLD"

```
URL: http://localhost/providers?category=public-weighing&location=Brisbane+City%2C+QLD
✅ PASSED - Location correctly URL-encoded
```

## Summary

All test queries demonstrate:

1. ✅ **Correct Intent Classification** - Calculator, guidance, providers, clarify, or location
2. ✅ **Accurate Category Matching** - All 16 service categories correctly identified
3. ✅ **Proper Location Handling** - Extraction, formatting, and URL encoding
4. ✅ **Safety Boundaries** - Appropriate disclaimers and source attribution
5. ✅ **No False Matches** - Unknown queries safely route to clarify without substitution
6. ✅ **Robust Edge Case Handling** - Case, whitespace, and device location scenarios

The VanAssist Ask function produces relevant, accurate results across a comprehensive range of real-world user queries for both TowSmart and TrailerWise brands.
