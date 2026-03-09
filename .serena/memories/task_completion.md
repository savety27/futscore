# Task Completion Checklist

Before finishing a task:
1. [ ] Ensure no debug statements (like `var_dump`, `print_r`, `echo` for debugging) are left in the code.
2. [ ] If database changes were made, ensure migrations or SQL scripts are provided.
3. [ ] Run existing tests related to the change: `phpunit -c phpunit.xml`.
4. [ ] Verify CSRF protection is maintained on any new forms.
5. [ ] Check that file uploads are handled securely and paths are correct.
