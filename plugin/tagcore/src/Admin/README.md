# Admin

Thin WordPress administration controllers and React entry-point integration
belong here. Capability and nonce checks occur at the boundary; SQL and domain
state machines do not.

RT-204 extends the Batch REST controller with the capability-protected
`POST tagcore/v1/batches/{batch_id}/generation` command. It accepts no
client-controlled generation fields, invokes the Application start/resume use
case, returns only aggregate progress and queue state, and applies no-store
headers. RT-204 does not change the RT-201 React interface; confirmation and
visible progress belong to RT-205.

RT-205 adds the authorized no-store GET projection and the existing React
detail-page confirmation, progress, queue-health, and retry experience. The UI
polls only while generation is active, pauses while hidden, renders committed
counts rather than queue estimates, and never exposes Tag IDs or provider
errors.
