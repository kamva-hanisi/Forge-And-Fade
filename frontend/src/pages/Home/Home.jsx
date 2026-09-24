import Hero from "../../components/Hero/Hero";

const Home = () => {
  return (
    <main>
      <Hero />

      <section>
        <div className="container">
          <span className="section-label">FORGE & FADE</span>

          <h1 className="section-title">
            Modern Cuts.
            <br />
            <span className="gold-text">Timeless Craft.</span>
          </h1>

          <p className="section-description">
            Precision cuts, sharp fades and timeless grooming crafted for the
            modern gentleman.
          </p>
        </div>
      </section>
    </main>
  );
};

export default Home;