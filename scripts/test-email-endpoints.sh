#!/bin/bash

##############################################################################
# Email Endpoints Health Check Script
# Continuously monitors the health of email API endpoints
##############################################################################

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
WATCH_MODE=false
INTERVAL=30
VERBOSE=false
FILTER="EmailControllerTest"

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -w|--watch)
            WATCH_MODE=true
            shift
            ;;
        -i|--interval)
            INTERVAL="$2"
            shift 2
            ;;
        -v|--verbose)
            VERBOSE=true
            shift
            ;;
        -f|--filter)
            FILTER="$2"
            shift 2
            ;;
        -h|--help)
            echo "Usage: $0 [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  -w, --watch           Run tests continuously in watch mode"
            echo "  -i, --interval SEC    Set interval between test runs (default: 30s)"
            echo "  -v, --verbose         Show detailed test output"
            echo "  -f, --filter PATTERN  Filter tests by pattern (default: EmailControllerTest)"
            echo "  -h, --help            Show this help message"
            echo ""
            echo "Examples:"
            echo "  $0                    # Run tests once"
            echo "  $0 -w                 # Watch mode with 30s interval"
            echo "  $0 -w -i 60           # Watch mode with 60s interval"
            echo "  $0 -v                 # Verbose output"
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            echo "Use -h or --help for usage information"
            exit 1
            ;;
    esac
done

# Function to print header
print_header() {
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}  Email Endpoints Health Check${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
}

# Function to run tests
run_tests() {
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')

    echo -e "${YELLOW}[$timestamp] Running email endpoint tests...${NC}"
    echo ""

    # Build test command
    local test_cmd="php artisan test --filter=$FILTER"

    if [ "$VERBOSE" = true ]; then
        test_cmd="$test_cmd --display-warnings"
    fi

    # Run tests and capture output
    local test_output
    local test_exit_code=0

    if test_output=$($test_cmd 2>&1); then
        test_exit_code=0
    else
        test_exit_code=$?
    fi

    # Parse test results
    local passed=$(echo "$test_output" | grep -oE '[0-9]+ passed' | grep -oE '[0-9]+' || echo "0")
    local failed=$(echo "$test_output" | grep -oE '[0-9]+ failed' | grep -oE '[0-9]+' || echo "0")
    local skipped=$(echo "$test_output" | grep -oE '[0-9]+ skipped' | grep -oE '[0-9]+' || echo "0")
    local duration=$(echo "$test_output" | grep -oE 'Duration: [0-9.]+s' | grep -oE '[0-9.]+' || echo "0")

    # Display results
    echo ""
    echo "Test Results:"
    echo -e "  ${GREEN}Passed:${NC}  $passed"
    echo -e "  ${YELLOW}Skipped:${NC} $skipped"

    if [ "$failed" -gt 0 ]; then
        echo -e "  ${RED}Failed:${NC}  $failed"
    fi

    echo -e "  Duration: ${duration}s"
    echo ""

    # Show verbose output if requested
    if [ "$VERBOSE" = true ]; then
        echo "$test_output"
        echo ""
    fi

    # Check test status
    if [ $test_exit_code -eq 0 ]; then
        echo -e "${GREEN}✓ All tests passed!${NC}"
        return 0
    else
        echo -e "${RED}✗ Some tests failed!${NC}"
        echo ""
        echo "Failed test output:"
        echo "$test_output" | grep -A 10 "FAILED" || echo "$test_output"
        return 1
    fi
}

# Function to check database connection
check_database() {
    echo -e "${YELLOW}Checking database connection...${NC}"

    if php artisan db:show --quiet 2>/dev/null; then
        echo -e "${GREEN}✓ Database connection OK${NC}"
        echo ""
        return 0
    else
        echo -e "${RED}✗ Database connection failed${NC}"
        echo ""
        return 1
    fi
}

# Function to verify migrations
check_migrations() {
    echo -e "${YELLOW}Verifying migrations...${NC}"

    local migration_output=$(php artisan migrate:status 2>&1)
    local pending=$(echo "$migration_output" | grep -c "Pending" || echo "0")

    if [ "$pending" -eq 0 ]; then
        echo -e "${GREEN}✓ All migrations are up to date${NC}"
        echo ""
        return 0
    else
        echo -e "${RED}✗ Found $pending pending migrations${NC}"
        echo ""
        echo "Run: php artisan migrate"
        return 1
    fi
}

# Main execution
main() {
    print_header

    # Pre-flight checks
    check_database || exit 1
    check_migrations || exit 1

    if [ "$WATCH_MODE" = true ]; then
        echo -e "${BLUE}Watch mode enabled - Tests will run every ${INTERVAL}s${NC}"
        echo -e "${BLUE}Press Ctrl+C to stop${NC}"
        echo ""

        # Run tests in loop
        while true; do
            run_tests
            echo ""
            echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
            echo -e "${YELLOW}Waiting ${INTERVAL}s before next run...${NC}"
            sleep "$INTERVAL"
            echo ""
        done
    else
        # Run tests once
        run_tests
        exit_code=$?
        echo ""
        exit $exit_code
    fi
}

# Run main function
main
