import re

with open('admin/views/subjects/page.php', 'r') as f:
    content = f.read()

# Read the CSS from users
with open('admin/views/users/page.php', 'r') as f:
    users_content = f.read()

# Extract <style>...</style> from users
users_style_match = re.search(r'(<style>.*?</style>)', users_content, re.DOTALL)
if not users_style_match:
    print("Could not find style in users")
    exit(1)

users_css = users_style_match.group(1)
# Replace user classes with subject classes
subject_css = users_css.replace('cbt-users-', 'cbt-subject-')
subject_css = subject_css.replace('cbt-users', 'cbt-subject') # Just in case

# Now replace the <style>...</style> block in subjects/page.php
content = re.sub(r'<style>.*?</style>', subject_css, content, count=1, flags=re.DOTALL)

# Re-style the bulk buttons in subjects
content = content.replace(
    'class="button button-secondary" name="bulk_mode" value="selected"',
    'class="button cbt-subject-bulk-button cbt-subject-bulk-button--selected" name="bulk_mode" value="selected"'
)
content = content.replace(
    'class="button button-secondary" name="bulk_mode" value="all"',
    'class="button cbt-subject-bulk-button cbt-subject-bulk-button--all" name="bulk_mode" value="all"'
)

with open('admin/views/subjects/page.php', 'w') as f:
    f.write(content)

print("done")
