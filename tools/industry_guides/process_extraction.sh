#!/bin/bash
# Automated extraction processing pipeline
# 
# Runs geocoding → validation → import for extracted data
#
# Usage:
#   ./tools/industry_guides/process_extraction.sh industry_big4_2026 industry_big4_holiday_guide_2026
#   ./tools/industry_guides/process_extraction.sh regional_drive_qld_2026 regional_drive_queensland_2026

set -e

if [ $# -lt 2 ]; then
    echo "Usage: $0 <seed_dir> <dataset_key>"
    echo "Example: $0 industry_big4_2026 industry_big4_holiday_guide_2026"
    exit 1
fi

SEED_DIR="$1"
DATASET_KEY="$2"
WORKSPACE_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SEEDS_PATH="$WORKSPACE_ROOT/database/seeds/$SEED_DIR"
JSON_FILE="$SEEDS_PATH/parks.json"

# Use facilities.json if parks.json doesn't exist
if [ ! -f "$JSON_FILE" ]; then
    JSON_FILE="$SEEDS_PATH/facilities.json"
fi

if [ ! -f "$JSON_FILE" ]; then
    echo "❌ Error: No JSON file found in $SEEDS_PATH"
    exit 1
fi

echo "🚀 Extraction Processing Pipeline"
echo "=================================="
echo "Seed directory: $SEED_DIR"
echo "Dataset key: $DATASET_KEY"
echo "JSON file: $JSON_FILE"
echo ""

# Step 1: Validation (pre-geocoding)
echo "📋 Step 1: Pre-geocoding validation..."
python3 "$WORKSPACE_ROOT/tools/industry_guides/validate_extraction.py" "$JSON_FILE"

VALIDATION_EXIT=$?
if [ $VALIDATION_EXIT -ne 0 ]; then
    echo "⚠️  Validation warnings found. Continuing..."
fi

# Step 2: Geocoding
echo ""
echo "🌍 Step 2: Geocoding facilities..."
BEFORE_COUNT=$(jq '[.[] | select(.latitude != null and .longitude != null)] | length' "$JSON_FILE")
echo "Facilities with coordinates before: $BEFORE_COUNT"

python3 "$WORKSPACE_ROOT/tools/industry_guides/geocode_facilities.py" "$JSON_FILE"

AFTER_COUNT=$(jq '[.[] | select(.latitude != null and .longitude != null)] | length' "$JSON_FILE")
echo "Facilities with coordinates after: $AFTER_COUNT"
echo "Newly geocoded: $(($AFTER_COUNT - $BEFORE_COUNT))"

# Step 3: Validation (post-geocoding)
echo ""
echo "📋 Step 3: Post-geocoding validation..."
python3 "$WORKSPACE_ROOT/tools/industry_guides/validate_extraction.py" "$JSON_FILE"

# Step 4: Import (dry-run)
echo ""
echo "📥 Step 4: Import dry-run..."
php "$WORKSPACE_ROOT/scripts/import-industry-guide.php" "$DATASET_KEY"

# Step 5: Import (actual)
echo ""
read -p "Import looks good? Apply to database? (y/N): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "📥 Step 5: Importing to database..."
    php "$WORKSPACE_ROOT/scripts/import-industry-guide.php" "$DATASET_KEY" --apply
    echo "✅ Import complete!"
else
    echo "⏭️  Skipped import. Run manually when ready:"
    echo "   php scripts/import-industry-guide.php $DATASET_KEY --apply"
fi

echo ""
echo "✅ Pipeline complete!"
