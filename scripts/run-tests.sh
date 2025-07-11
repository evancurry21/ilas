#!/bin/bash

# ILAS Comprehensive Test Runner Script
# This script runs all automated tests for the Idaho Legal Aid Services integration

set -e

echo "================================================"
echo "Idaho Legal Aid Services - Test Suite"
echo "================================================"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test results
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# Function to run a test category
run_test_category() {
    local category=$1
    local command=$2
    
    echo -e "${YELLOW}Running $category tests...${NC}"
    
    if eval "$command"; then
        echo -e "${GREEN}✓ $category tests passed${NC}"
        ((PASSED_TESTS++))
    else
        echo -e "${RED}✗ $category tests failed${NC}"
        ((FAILED_TESTS++))
    fi
    
    ((TOTAL_TESTS++))
    echo ""
}

# Change to project directory
cd /home/evancurry/ilas

# 1. PHP Code Standards
run_test_category "PHP Coding Standards" \
    "ddev exec phpcs --standard=Drupal,DrupalPractice web/modules/custom"

# 2. PHP Unit Tests
run_test_category "PHPUnit" \
    "ddev exec phpunit -c web/core web/modules/custom/*/tests/src/Unit"

# 3. Kernel Tests
run_test_category "Kernel" \
    "ddev exec phpunit -c web/core web/modules/custom/*/tests/src/Kernel"

# 4. Functional Tests
run_test_category "Functional" \
    "ddev exec phpunit -c web/core web/modules/custom/*/tests/src/Functional"

# 5. JavaScript Tests
run_test_category "JavaScript" \
    "ddev exec npm test --prefix web/themes/custom/b5subtheme"

# 6. Behat Tests
if [ -f "behat.yml" ]; then
    run_test_category "Behat" \
        "ddev exec behat"
fi

# 7. Security Audit
run_test_category "Security" \
    "ddev composer audit"

# 8. Database Integrity
run_test_category "Database Integrity" \
    "ddev drush sql:query 'SELECT 1' && echo 'Database connection OK'"

# 9. CiviCRM API Test
run_test_category "CiviCRM API" \
    "ddev drush ev 'civicrm_initialize(); \$result = civicrm_api3(\"System\", \"get\", []); print \$result[\"is_error\"] == 0 ? \"OK\" : \"FAIL\";'"

# 10. Configuration Validation
run_test_category "Configuration" \
    "ddev drush config:status"

# 11. Performance Check
echo -e "${YELLOW}Running performance checks...${NC}"
HOMEPAGE_LOAD_TIME=$(curl -o /dev/null -s -w '%{time_total}' https://ilas.ddev.site/)
if (( $(echo "$HOMEPAGE_LOAD_TIME < 2.0" | bc -l) )); then
    echo -e "${GREEN}✓ Homepage load time: ${HOMEPAGE_LOAD_TIME}s${NC}"
    ((PASSED_TESTS++))
else
    echo -e "${RED}✗ Homepage load time: ${HOMEPAGE_LOAD_TIME}s (threshold: 2.0s)${NC}"
    ((FAILED_TESTS++))
fi
((TOTAL_TESTS++))

# 12. Accessibility Check (basic)
run_test_category "Accessibility (Basic)" \
    "ddev exec pa11y https://ilas.ddev.site/ --standard WCAG2AA"

# Generate test report
echo ""
echo "================================================"
echo "Test Summary"
echo "================================================"
echo "Total Tests: $TOTAL_TESTS"
echo -e "Passed: ${GREEN}$PASSED_TESTS${NC}"
echo -e "Failed: ${RED}$FAILED_TESTS${NC}"

PASS_RATE=$((PASSED_TESTS * 100 / TOTAL_TESTS))
echo "Pass Rate: $PASS_RATE%"

# Generate detailed report
REPORT_FILE="test-results-$(date +%Y%m%d-%H%M%S).txt"
{
    echo "Idaho Legal Aid Services - Test Results"
    echo "Generated: $(date)"
    echo ""
    echo "Summary:"
    echo "- Total Tests: $TOTAL_TESTS"
    echo "- Passed: $PASSED_TESTS"
    echo "- Failed: $FAILED_TESTS"
    echo "- Pass Rate: $PASS_RATE%"
    echo ""
    echo "Environment:"
    echo "- PHP Version: $(ddev exec php -v | head -n 1)"
    echo "- Drupal Version: $(ddev drush status --field=drupal-version)"
    echo "- CiviCRM Version: $(ddev drush ev 'civicrm_initialize(); print CRM_Utils_System::version();')"
} > "$REPORT_FILE"

echo ""
echo "Detailed report saved to: $REPORT_FILE"

# Exit with appropriate code
if [ $FAILED_TESTS -eq 0 ]; then
    echo -e "${GREEN}All tests passed!${NC}"
    exit 0
else
    echo -e "${RED}Some tests failed. Please review the results.${NC}"
    exit 1
fi