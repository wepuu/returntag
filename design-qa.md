# RT-201 Product Design QA

## Comparison target

- Source visual truth: `C:\Users\admin\.codex\generated_images\019f7363-9de3-7430-802c-e98d01bc3064\call_8VILQS2V1mcf7csDoZRAtQtn.png`
- Source pixel dimensions: `1487 × 1058`
- Implementation route: WordPress administration page `admin.php?page=tagcore-batches&view=create`
- Final implementation screenshot: `artifacts/design-qa/rt-201-create-duplicate-error-final.png`
- Browser viewport: `1440 × 1024` CSS pixels
- Implementation pixel dimensions: `1425 × 1013`
- Density normalization: the source was resized to `1425 × 1013` before comparison
- State: create-Batch form with a duplicate Batch Code validation error

## Full-view comparison evidence

- Combined comparison: `artifacts/design-qa/rt-201-final-comparison.png`
- The implementation preserves the selected direction's information hierarchy:
  the error summary, server-controlled values, three-stage progress rail,
  two-column form, and primary/secondary actions all remain visible in one
  desktop viewport.
- WordPress administration chrome, typography, controls, focus treatment, and
  iconography use WordPress-provided components or assets.

## Focused region comparison evidence

- Combined focused comparison:
  `artifacts/design-qa/rt-201-final-focus-comparison.png`
- The duplicate-code summary receives focus and links the field name to the
  specific validation message.
- The field-level error is adjacent to Batch Code, while the tag-type radios
  remain horizontal and the Product stage remains current.
- A `782 × 900` narrow-screen check was also captured at
  `artifacts/design-qa/rt-201-mobile.png`; the status band and form collapse
  without visible horizontal overflow.

## Findings and resolution

- Initial error treatment was too visually heavy and repeated the detailed
  message. It was replaced with a white, red-bordered summary using a real
  WordPress caution icon and a generic heading.
- Tag-type radios initially stacked vertically. They now match the selected
  horizontal control group.
- The progress rail initially marked Production as current. It now marks
  Identity complete and Product current after a Batch Code is entered.
- The initial form density pushed the actions below the target viewport. Notice
  spacing and Notes height were reduced so both actions remain visible.

No unresolved P0, P1, or P2 visual issue remains.

## Intentional contract differences

- The reference includes future TagCore submenu destinations. RT-201 exposes
  only the authorized Batches destination.
- Model code, manufacturer, and sales channel remain optional according to the
  RT-201 and Schema 8 contracts; the reference depicts some as required.
- Manufacturer remains a text input because RT-201 does not introduce a
  manufacturer directory.
- The local WordPress locale formats the UTC timestamp in Chinese and identifies
  the actual current user (`admin`) rather than the reference fixture
  (`Operator`).

## Functional and console verification

- Successful draft creation was completed with server-controlled Draft status,
  generated quantity `0`, and activation disabled.
- “Create another” returned to a clean form.
- Duplicate Batch Code submission produced HTTP conflict behavior, focused the
  error summary, and preserved entered values.
- The final browser console contained no warnings or errors.

## Comparison history

1. Initial capture identified the stacked radios, heavy error notice, incorrect
   active progress stage, and cropped actions.
2. The first visual correction aligned the error, progress, and radio behavior.
3. The final density correction brought the action row into the target viewport.
4. Full-view and focused comparisons confirmed the final state.

final result: passed
